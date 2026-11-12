<?php

declare(strict_types=1);

namespace PackioTest\Feature\Webhook;

use Common\Security\SodiumSecretEncryptor;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Infrastructure\Cli\SourceWebhookSecretsEncryptCommand;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Http\TestResponse;
use PackioTest\Support\Http\WebhookTestHelpers;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function dirname;
use function json_encode;
use function random_bytes;

final class SourceWebhookSecretEncryptionFeatureTest extends FeatureTestCase
{
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
    public function itAcceptsPushSignedWithTheSecretShownForTheSource(): void
    {
        $sourceDto = $this->createSource();

        $this->push($sourceDto, (string) $sourceDto->providerConfig['webhook_secret'])->assertStatus(202);
        self::assertStringStartsWith('secretbox:v1:', $this->storedSecret($sourceDto->id));
    }

    #[Test]
    public function itKeepsLegacyPlaintextSecretsWorkingAndEncryptsThemWithTheCommand(): void
    {
        $sourceDto = $this->createSource();
        $this->storeRawSecret($sourceDto->id, 'legacy-plaintext-secret');

        $this->push($sourceDto, 'legacy-plaintext-secret')->assertStatus(202);

        self::assertStringContainsString('dry_run=1', $this->runEncryptCommand([
            '--dry-run' => true,
        ]));
        self::assertSame('legacy-plaintext-secret', $this->storedSecret($sourceDto->id));

        self::assertStringContainsString('encrypted=1', $this->runEncryptCommand());
        self::assertStringStartsWith('secretbox:v1:', $this->storedSecret($sourceDto->id));
        $this->rebuildContainer();
        $this->push($sourceDto, 'legacy-plaintext-secret')->assertStatus(202);

        self::assertStringContainsString('encrypted=0', $this->runEncryptCommand());
    }

    #[Test]
    public function itStoresTheRotatedSecretEncryptedAndShowsTheNewPlaintext(): void
    {
        $sourceDto = $this->createSource();
        $oldSecret = (string) $sourceDto->providerConfig['webhook_secret'];
        $sources   = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sources);

        $rotated = $sources->rotateWebhookSecretInContext(
            new ActorContext($sourceDto->organizationId, 'owner-id', 'owner'),
            $sourceDto->id,
        );

        self::assertInstanceOf(SourceDto::class, $rotated);
        $newSecret = (string) $rotated->providerConfig['webhook_secret'];
        self::assertNotSame('', $newSecret);
        self::assertNotSame($oldSecret, $newSecret);
        $stored = $this->storedSecret($sourceDto->id);
        self::assertStringStartsWith('secretbox:v1:', $stored);
        self::assertNotSame($newSecret, $stored);
        $this->push($rotated, $newSecret)->assertStatus(202);
        $this->push($rotated, $oldSecret)->assertStatus(401);
    }

    #[Test]
    public function itRejectsDeliveriesWhenTheStoredSecretCannotBeDecrypted(): void
    {
        $sourceDto = $this->createSource();
        $this->storeRawSecret($sourceDto->id, new SodiumSecretEncryptor('rotated-away-key')->encrypt('old-secret'));

        $testResponse = $this->push($sourceDto, 'old-secret');

        self::assertLessThan(500, $testResponse->status());
        self::assertGreaterThanOrEqual(400, $testResponse->status());
        self::assertSame('Webhook is not configured.', $testResponse->json()['message'] ?? null);
    }

    private function push(SourceDto $sourceDto, string $secret): TestResponse
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
            $this->githubHeaders($payload, $secret, 'push'),
            true,
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runEncryptCommand(array $input = []): string
    {
        $command = $this->getService(SourceWebhookSecretsEncryptCommand::class);
        self::assertInstanceOf(SourceWebhookSecretsEncryptCommand::class, $command);
        $commandTester = new CommandTester($command);
        self::assertSame(0, $commandTester->execute($input));

        return $commandTester->getDisplay();
    }

    private function createSource(): SourceDto
    {
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizations);
        $sources = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sources);

        return $sources->registerSource(new SourceRegistrationRequestDto(
            'github',
            'https://github.com/org/' . bin2hex(random_bytes(6)),
            $organizations->registerOrganization('webhook-secret-org-' . bin2hex(random_bytes(6)))->id,
        ));
    }

    private function storeRawSecret(string $sourceId, string $secret): void
    {
        $this->database()->execute(
            "UPDATE sources SET provider_config = jsonb_set(provider_config::jsonb, '{webhook_secret}', ?::jsonb)::json WHERE uuid = ?",
            [json_encode($secret), $sourceId],
        );
        $this->rebuildContainer();
    }

    private function storedSecret(string $sourceId): string
    {
        $rows = $this->database()->query(
            "SELECT provider_config->>'webhook_secret' AS webhook_secret FROM sources WHERE uuid = ?",
            [$sourceId],
        )->fetchAll();

        return (string) ($rows[0]['webhook_secret'] ?? '');
    }

    private function database(): DatabaseInterface
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        return $databaseProvider->database();
    }
}
