<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Migrations\Capsule;
use Migration\BackfillSourceDeletionRunIds;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function random_bytes;
use function sprintf;

require_once __DIR__ . '/../../../../../../data/db/cycle/migrations/20260823.232000_0_0_default_backfill_source_deletion_run_ids.php';

final class SourceDeletionRunMigrationTest extends IntegrationTestCase
{
    #[Test]
    public function itBackfillsAWorkerLeasedLegacySourceDeletionJob(): void
    {
        $database       = $this->database();
        $originalSchema = (string) $database->query('SELECT current_schema()')->fetchColumn();
        $schema         = 'packio_source_deletion_migration_' . bin2hex(random_bytes(8));
        $sourceId       = 'legacy-deleting-source';

        $database->execute('CREATE SCHEMA ' . $schema);

        try {
            $database->execute('SET search_path TO ' . $schema);
            $database->execute('CREATE TABLE sources (uuid varchar(255) PRIMARY KEY, organization_id varchar(255) NOT NULL, status varchar(255) NOT NULL, deletion_run_id varchar(36) NULL, updated_at timestamp NULL)');
            $database->execute('CREATE TABLE jobs (uuid varchar(255) PRIMARY KEY, type varchar(255) NOT NULL, status varchar(255) NOT NULL, payload jsonb NOT NULL)');
            $database->execute("INSERT INTO sources (uuid, organization_id, status) VALUES (?, 'legacy-org', 'deleting')", [$sourceId]);
            $database->execute(
                "INSERT INTO jobs (uuid, type, status, payload) VALUES ('legacy-running-job', 'source.delete', 'running', '{\"source_id\": \"legacy-deleting-source\"}')",
            );

            new BackfillSourceDeletionRunIds()
                ->withCapsule(new Capsule($database))
                ->up()
            ;

            $deletionRunId = $database->query(
                'SELECT deletion_run_id FROM sources WHERE uuid = ?',
                [$sourceId],
            )->fetchColumn();
            self::assertIsString($deletionRunId);
            self::assertNotSame('', $deletionRunId);
            self::assertSame(
                $deletionRunId,
                $database->query("SELECT payload->>'deletion_run_id' FROM jobs WHERE uuid = 'legacy-running-job'")->fetchColumn(),
            );
        } finally {
            $database->execute('SET search_path TO ' . $originalSchema);
            $database->execute(sprintf('DROP SCHEMA IF EXISTS %s CASCADE', $schema));
        }
    }

    private function database(): DatabaseInterface
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        return $databaseProvider->database();
    }
}
