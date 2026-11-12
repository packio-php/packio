<?php

declare(strict_types=1);

namespace PackioTest\Integration\Webhook;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\ForkedChildProcess;
use PackioTest\Support\Base\IntegrationTestCase;
use PackioTest\Support\Http\WebhookTestHelpers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

use function bin2hex;
use function dirname;
use function fclose;
use function fread;
use function function_exists;
use function fwrite;
use function hrtime;
use function json_decode;
use function json_encode;
use function pcntl_fork;
use function random_bytes;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function usleep;

#[Group('slow')]
final class WebhookDuplicateDeliveryRaceTest extends IntegrationTestCase
{
    use ForkedChildProcess;
    use WebhookTestHelpers;

    protected function setUp(): void
    {
        $config                                              = require dirname(__DIR__, 3) . '/config/config.php';
        $config['sources']['providers']['github']['enabled'] = true;
        $config['sources']['webhooks']['github']             = [
            'signature_header' => 'X-Hub-Signature-256',
            'events'           => ['push'],
        ];
        $this->serviceOverrides['config']                  = $config;
        $this->serviceOverrides['config.sources']          = $config['sources'];
        $this->serviceOverrides['config.sources.webhooks'] = $config['sources']['webhooks'];
        parent::setUp();
    }

    #[Test]
    public function concurrentIdenticalDeliveriesAreAnsweredAsDuplicatesAndScheduleOneSync(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $sourceDto = $this->createSource();
        $delivery  = bin2hex(random_bytes(8));
        $payload   = $this->fixtureJson('github', 'push', [
            'repository' => [
                'html_url' => $sourceDto->url,
            ],
        ]);
        $headers = [
            'content-type'        => 'application/json',
            'x-github-event'      => 'push',
            'x-github-delivery'   => $delivery,
            'x-hub-signature-256' => $this->githubWebhookSignature($payload, (string) $sourceDto->providerConfig['webhook_secret']),
        ];

        $errorPath = tempnam(sys_get_temp_dir(), 'packio-webhook-race-errors-');
        self::assertIsString($errorPath);
        $this->disconnectDatabaseBeforeFork();

        // A held, uncommitted insert of the same delivery makes both requests miss the
        // duplicate read and block on the unique index, which reproduces the race exactly.
        [$lockedParent, $lockedChild]   = $this->socketPair();
        [$releaseParent, $releaseChild] = $this->socketPair();
        $holderPid                      = $this->forkDeliveryHolder($sourceDto->id, $delivery, $lockedChild, $releaseChild, $errorPath);
        fclose($lockedChild);
        fclose($releaseChild);
        self::assertSame('1', fread($lockedParent, 1), $this->childErrors($errorPath));
        fclose($lockedParent);

        $requests = [];
        for ($i = 0; $i < 2; ++$i) {
            [$resultParent, $resultChild] = $this->socketPair();
            $requests[]                   = [$this->forkWebhookRequest($sourceDto->id, $headers, $payload, $resultChild), $resultParent];
            fclose($resultChild);
        }

        $this->waitForBlockedInserts(2);
        fwrite($releaseParent, '1');
        fclose($releaseParent);
        $this->assertChildExitedSuccessfully($holderPid, $errorPath);

        $results = [];
        foreach ($requests as [$pid, $resultParent]) {
            $results[] = json_decode((string) stream_get_contents($resultParent), true);
            fclose($resultParent);
            $this->assertChildExitedSuccessfully($pid, $errorPath);
        }
        @unlink($errorPath);

        $expected = [202, 'Sync scheduled for ' . $sourceDto->url . '.'];
        self::assertSame([$expected, $expected], $results);

        $jobRepository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobRepository);
        self::assertCount(1, $jobRepository->findByOrganization($sourceDto->organizationId, [
            'type'  => 'source.sync',
            'limit' => 20,
        ]));
    }

    /**
     * @param resource $lockedSocket
     * @param resource $releaseSocket
     */
    private function forkDeliveryHolder(
        string $sourceId,
        string $delivery,
        mixed $lockedSocket,
        mixed $releaseSocket,
        string $errorPath
    ): int {
        $pid = pcntl_fork();
        self::assertNotFalse($pid);
        if (0 !== $pid) {
            return $pid;
        }

        try {
            $database = $this->database();
            $database->begin();
            $repository = $this->getService(WebhookEventRepositoryInterface::class);
            self::assertInstanceOf(WebhookEventRepositoryInterface::class, $repository);
            $repository->save(WebhookEvent::receive($sourceId, 'github', $delivery, 'push', 'holder'));
            fwrite($lockedSocket, '1');
            fclose($lockedSocket);
            @fread($releaseSocket, 1);
            $database->rollback();

            exit(0);
        } catch (Throwable $throwable) {
            $this->recordChildError($errorPath, $throwable);

            exit(1);
        }
    }

    /**
     * @param array<string, string> $headers
     * @param resource              $resultSocket
     */
    private function forkWebhookRequest(string $sourceId, array $headers, string $payload, mixed $resultSocket): int
    {
        $pid = pcntl_fork();
        self::assertNotFalse($pid);
        if (0 !== $pid) {
            return $pid;
        }

        try {
            $webhookApi = $this->getService(WebhookApiFacadeInterface::class);
            self::assertInstanceOf(WebhookApiFacadeInterface::class, $webhookApi);
            $result = $webhookApi->handleWebhook($sourceId, $headers, $payload, 'github');
            $answer = [$result->statusCode, $result->message];
        } catch (Throwable) {
            // An escaping exception is what the HTTP layer turns into a 500.
            $answer = [500, 'exception'];
        }
        fwrite($resultSocket, (string) json_encode($answer));
        fclose($resultSocket);

        exit(0);
    }

    private function waitForBlockedInserts(int $count): void
    {
        $deadline = hrtime(true) + 10_000_000_000;
        do {
            $blocked = (int) $this->database()->query(
                "SELECT COUNT(*) FROM pg_stat_activity WHERE datname = current_database() AND wait_event_type = 'Lock' AND query ILIKE 'INSERT INTO%webhook_events%'",
            )->fetchColumn();
            if ($blocked >= $count) {
                return;
            }
            usleep(20_000);
        } while (hrtime(true) < $deadline);

        self::fail('Webhook requests did not block on the held delivery insert.');
    }

    private function database(): DatabaseInterface
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        return $databaseProvider->database();
    }

    private function createSource(): SourceDto
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertInstanceOf(IdentityApiFacadeInterface::class, $identity);
        $userDto = $identity->createUser('webhook-race-' . bin2hex(random_bytes(6)) . '@example.test', 'Webhook User', 'Password1!');
        $identity->markUserEmailAsVerified($userDto->id);

        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizations);
        $organizationDto = $organizations->registerOrganization('webhook-race-org-' . bin2hex(random_bytes(6)), $userDto->id);

        $sources = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sources);

        return $sources->registerSource(new SourceRegistrationRequestDto(
            'github',
            'https://github.com/org/' . bin2hex(random_bytes(6)),
            $organizationDto->id,
        ));
    }
}
