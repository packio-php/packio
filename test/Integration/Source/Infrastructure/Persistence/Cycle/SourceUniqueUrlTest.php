<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\Exception\StatementException\ConstrainException;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SourceUniqueUrlTest extends IntegrationTestCase
{
    #[Test]
    public function theDatabaseRefusesASecondSourceForTheSameRepositoryInOneOrganization(): void
    {
        $sources = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sources);
        $sourceRegistrationRequestDto = new SourceRegistrationRequestDto('github', 'https://github.com/org/unique-url', 'organization-unique-url');
        $sourceDto                    = $sources->registerSource($sourceRegistrationRequestDto);

        // Registering again reuses the source, so a duplicate can only be written past the application.
        self::assertSame($sourceDto->id, $sources->registerSource($sourceRegistrationRequestDto)->id);

        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $database    = $databaseProvider->database();
        $row         = $database->select()->from('sources')->where('uuid', $sourceDto->id)->fetchAll()[0];
        $row['uuid'] = '00000000-0000-7000-8000-000000000001';

        $this->expectException(ConstrainException::class);
        $database->insert('sources')->values($row)->run();
    }
}
