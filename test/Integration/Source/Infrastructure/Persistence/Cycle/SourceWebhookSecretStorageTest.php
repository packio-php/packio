<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseProviderInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function random_bytes;

final class SourceWebhookSecretStorageTest extends IntegrationTestCase
{
    #[Test]
    public function itStoresTheWebhookSecretEncryptedAndExposesPlaintextInTheDto(): void
    {
        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization('webhook-secret-' . bin2hex(random_bytes(6)));
        $sourceDto       = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/webhook-secret-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));

        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $rows = $databaseProvider->database()->query(
            "SELECT provider_config->>'webhook_secret' AS webhook_secret FROM sources WHERE uuid = ?",
            [$sourceDto->id],
        )->fetchAll();

        $plaintext = (string) ($sourceDto->providerConfig['webhook_secret'] ?? '');
        $stored    = (string) ($rows[0]['webhook_secret'] ?? '');
        self::assertNotSame('', $plaintext);
        self::assertStringStartsWith('secretbox:v1:', $stored);
        self::assertNotSame($plaintext, $stored);
    }
}
