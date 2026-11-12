<?php

declare(strict_types=1);

namespace PackioTest\Feature\Webhook;

use ArrayObject;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Webhook\Application\Dto\SourceData;
use Packio\Webhook\Application\Port\SourcePort;
use Packio\Webhook\Infrastructure\Adapter\SourceApiFacadeAdapter;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Http\WebhookTestHelpers;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Stringable;

use function array_filter;
use function array_values;
use function bin2hex;
use function dirname;
use function random_bytes;

final class WebhookSyncSchedulingFailureFeatureTest extends FeatureTestCase
{
    use WebhookTestHelpers;

    private object $sourcePort;

    private string $userId;

    /** @var ArrayObject<int, array{level: mixed, message: string, context: array<mixed>}> */
    private ArrayObject $arrayObject;

    protected function setUp(): void
    {
        $config                                              = require dirname(__DIR__, 3) . '/config/config.php';
        $config['sources']['providers']['github']['enabled'] = true;
        $webhooks                                            = $config['sources']['webhooks'] ?? [];
        $webhooks['github']                                  = [
            'signature_header' => 'X-Hub-Signature-256',
            'events'           => ['push'],
        ];
        $config['sources']['webhooks'] = $webhooks;

        // Fails the first scheduling call, then delegates to the real adapter.
        $this->sourcePort = new class implements SourcePort {
            public ?SourcePort $inner = null;

            public bool $failed = false;

            public function findSource(string $sourceId): ?SourceData
            {
                return $this->inner?->findSource($sourceId);
            }

            public function scheduleSourceSyncOnce(SourceData $sourceData, string $idempotencyKey): string
            {
                if (! $this->failed) {
                    $this->failed = true;

                    throw new RuntimeException('SQLSTATE[08006] connection to queue lost');
                }

                return (string) $this->inner?->scheduleSourceSyncOnce($sourceData, $idempotencyKey);
            }
        };
        $this->arrayObject = new ArrayObject();
        $logger            = new class($this->arrayObject) extends AbstractLogger {
            /** @param ArrayObject<int, array{level: mixed, message: string, context: array<mixed>}> $arrayObject */
            public function __construct(private readonly ArrayObject $arrayObject) {}

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->arrayObject[] = [
                    'level'   => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        $this->serviceOverrides['config']                  = $config;
        $this->serviceOverrides['config.sources']          = $config['sources'];
        $this->serviceOverrides['config.sources.webhooks'] = $webhooks;
        $this->serviceOverrides[SourcePort::class]         = $this->sourcePort;
        $this->serviceOverrides[LoggerInterface::class]    = $logger;
        parent::setUp();

        /** @var SourceApiFacadeInterface $sources */
        $sources                 = $this->getService(SourceApiFacadeInterface::class);
        $this->sourcePort->inner = new SourceApiFacadeAdapter($sources);
    }

    #[Test]
    public function itHidesSchedulingErrorDetailsAndLogsThem(): void
    {
        $sourceDto = $this->createSource();
        $delivery  = $this->webhookDeliveryId();

        $testResponse = $this->sendPush($sourceDto, $delivery);

        $testResponse->assertStatus(500);
        self::assertSame('Failed to schedule sync for ' . $sourceDto->url . '.', $testResponse->json()['message'] ?? null);

        /** @var WebhookApiFacadeInterface $webhookApi */
        $webhookApi = $this->getService(WebhookApiFacadeInterface::class);
        $events     = $webhookApi->recentEvents($sourceDto->id, 1);
        self::assertSame('sync_schedule_failed', $events[0]->status);
        self::assertStringNotContainsString('SQLSTATE', $events[0]->message);

        $errors = array_values(array_filter($this->arrayObject->getArrayCopy(), static fn (array $record): bool => 'error' === $record['level']));
        self::assertCount(1, $errors);
        self::assertSame($sourceDto->id, $errors[0]['context']['sourceId'] ?? null);
        self::assertSame($delivery, $errors[0]['context']['deliveryId'] ?? null);
        self::assertSame(RuntimeException::class, $errors[0]['context']['class'] ?? null);
        self::assertArrayHasKey('trace', $errors[0]['context']);
    }

    #[Test]
    public function itRetriesSchedulingWhenFailedDeliveryIsRedelivered(): void
    {
        $sourceDto = $this->createSource();
        $delivery  = $this->webhookDeliveryId();

        $this->sendPush($sourceDto, $delivery)->assertStatus(500);

        $testResponse = $this->sendPush($sourceDto, $delivery);

        $testResponse->assertStatus(202);
        self::assertSame('Sync scheduled for ' . $sourceDto->url . '.', $testResponse->json()['message'] ?? null);

        /** @var WebhookApiFacadeInterface $webhookApi */
        $webhookApi = $this->getService(WebhookApiFacadeInterface::class);
        $events     = $webhookApi->recentEvents($sourceDto->id, 5);
        self::assertCount(1, $events);
        self::assertSame('processed', $events[0]->status);
        self::assertNotNull($events[0]->scheduledJobId);
        self::assertSame(0, $events[0]->reprocessCount);

        /** @var JobRepositoryInterface $jobs */
        $jobs     = $this->getService(JobRepositoryInterface::class);
        $syncJobs = $jobs->findByOrganization($sourceDto->organizationId, [
            'type'  => 'source.sync',
            'limit' => 20,
        ]);
        self::assertCount(1, $syncJobs);
        self::assertSame($sourceDto->id, $syncJobs[0]->payload()['source_id'] ?? null);
    }

    #[Test]
    public function itDoesNotRetrySchedulingForSourceDeactivatedAfterFailure(): void
    {
        $sourceDto = $this->createSource();
        $delivery  = $this->webhookDeliveryId();

        $this->sendPush($sourceDto, $delivery)->assertStatus(500);

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);
        $sourceApi->updateSettingsInContext(
            new ActorContext($sourceDto->organizationId, $this->userId, 'owner'),
            $sourceDto->id,
            $sourceDto->visibility,
            false,
            $sourceDto->keepLastReleases,
        );
        self::assertFalse($sourceApi->findSource($sourceDto->id)?->active);

        $testResponse = $this->sendPush($sourceDto, $delivery);

        $testResponse->assertStatus(500);
        self::assertSame('Failed to schedule sync for ' . $sourceDto->url . '.', $testResponse->json()['message'] ?? null);

        /** @var WebhookApiFacadeInterface $webhookApi */
        $webhookApi = $this->getService(WebhookApiFacadeInterface::class);
        $events     = $webhookApi->recentEvents($sourceDto->id, 5);
        self::assertCount(1, $events);
        self::assertSame('sync_schedule_failed', $events[0]->status);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertCount(0, $jobs->findByOrganization($sourceDto->organizationId, [
            'type'  => 'source.sync',
            'limit' => 20,
        ]));
    }

    private function sendPush(SourceDto $sourceDto, string $delivery): mixed
    {
        $payload = $this->fixtureJson('github', 'push', [
            'repository' => [
                'html_url' => $sourceDto->url,
            ],
        ]);

        return $this->request(
            'POST',
            '/webhooks/github/' . $sourceDto->id,
            $payload,
            $this->githubHeaders(
                $payload,
                (string) $sourceDto->providerConfig['webhook_secret'],
                'push',
                [
                    'X-GitHub-Delivery' => $delivery,
                ],
            ),
            true,
        );
    }

    private function createSource(): SourceDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            'webhook-sched-' . bin2hex(random_bytes(6)) . '@example.test',
            'Webhook User',
            'Password1!',
        );
        $identity->markUserEmailAsVerified($userDto->id);
        $this->userId = $userDto->id;

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations   = $this->getService(OrganizationApiFacadeInterface::class);
        $organizationDto = $organizations->registerOrganization('webhook-org-' . bin2hex(random_bytes(6)), $userDto->id);

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);

        return $sources->registerSource(new SourceRegistrationRequestDto(
            'github',
            'https://github.com/org/' . bin2hex(random_bytes(6)),
            $organizationDto->id,
        ));
    }
}
