<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Facade;

use const JSON_THROW_ON_ERROR;

use Common\Security\SodiumSecretEncryptor;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Application\Dto\WebhookRegistrationResult;
use Packio\Source\Application\Service\WebhookRegistrarInterface;
use Packio\Source\Application\Service\WebhookRegistrarRegistryInterface;
use Packio\Source\Domain\Entity\Source;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function json_encode;
use function random_bytes;

final class SourceApiFacadeWebhookRegistrationTest extends IntegrationTestCase
{
    #[Test]
    public function manualRegistrationMapsTheApplicationResultThroughTheRealObjectMapper(): void
    {
        $this->stubRegistrar();

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization('webhook-register-' . bin2hex(random_bytes(6)));
        $sourceDto       = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/webhook-register-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));

        $webhookRegistrationResultDto = $sourceApiFacade->tryRegisterWebhook($sourceDto->id);

        self::assertTrue($webhookRegistrationResultDto->success);
        self::assertSame('hook-42', $webhookRegistrationResultDto->externalId);
    }

    #[Test]
    public function manualRegistrationReplacesAStoredSecretThatCannotBeDecrypted(): void
    {
        $this->stubRegistrar();

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization('webhook-undecryptable-' . bin2hex(random_bytes(6)));
        $sourceDto       = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/webhook-undecryptable-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));
        $foreign          = new SodiumSecretEncryptor('rotated-away-key')->encrypt('old-secret');
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $databaseProvider->database()->execute(
            "UPDATE sources SET provider_config = jsonb_set(provider_config::jsonb, '{webhook_secret}', ?::jsonb)::json WHERE uuid = ?",
            [json_encode($foreign, JSON_THROW_ON_ERROR), $sourceDto->id],
        );
        $this->rebuildContainer();
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);
        self::assertSame('', $sourceApiFacade->findSource($sourceDto->id)?->providerConfig['webhook_secret'] ?? null);

        self::assertTrue($sourceApiFacade->tryRegisterWebhook($sourceDto->id)->success);

        $replaced = $sourceApiFacade->findSource($sourceDto->id);
        self::assertInstanceOf(SourceDto::class, $replaced);
        self::assertNotSame('', $replaced->providerConfig['webhook_secret'] ?? '');
    }

    private function stubRegistrar(): void
    {
        $registrar = new class implements WebhookRegistrarInterface {
            public function register(Source $source): WebhookRegistrationResult
            {
                return WebhookRegistrationResult::success('hook-42', 'Webhook registered.');
            }

            public function deregister(Source $source): bool
            {
                return true;
            }

            public function provider(): string
            {
                return 'github';
            }
        };

        $this->serviceOverrides[WebhookRegistrarRegistryInterface::class] = new readonly class($registrar) implements WebhookRegistrarRegistryInterface {
            public function __construct(private WebhookRegistrarInterface $webhookRegistrar) {}

            public function hasProvider(string $provider): bool
            {
                return 'github' === $provider;
            }

            public function forProvider(string $provider): ?WebhookRegistrarInterface
            {
                return $this->hasProvider($provider) ? $this->webhookRegistrar : null;
            }
        };
        $this->rebuildContainer();
    }
}
