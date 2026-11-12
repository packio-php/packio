<?php

declare(strict_types=1);

namespace PackioTest\Integration\Identity\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Migrations\Capsule;
use Migration\OrmDefault5ed7ab1bb79ef592c5fe380cd812392f;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

require_once __DIR__ . '/../../../../../../data/db/cycle/migrations/20260907.183620_0_0_default_change_users_add_index_users_email_uuid_idx.php';

final class UserEmailUuidIndexMigrationTest extends IntegrationTestCase
{
    #[Test]
    public function itAppliesAndRollsBackTheEmailUuidIndex(): void
    {
        $provider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $provider);
        $database       = $provider->database();
        $migration      = new OrmDefault5ed7ab1bb79ef592c5fe380cd812392f()->withCapsule(new Capsule($database));

        $migration->down();
        self::assertFalse($this->hasIndex($database));

        $migration->up();
        self::assertTrue($this->hasIndex($database));
    }

    private function hasIndex(DatabaseInterface $database): bool
    {
        return 'users_email_uuid_idx' === $database->query(
            "SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'users' AND indexname = 'users_email_uuid_idx'",
        )->fetchColumn();
    }
}
