<?php

declare(strict_types=1);

namespace PackioTest\Integration\Audit\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function random_bytes;
use function sprintf;

final class AuditSubjectMigrationTest extends IntegrationTestCase
{
    #[Test]
    public function itBackfillsRequiredAuditSubjectsWhenApplyingToAPopulatedPostgresTable(): void
    {
        $database       = $this->database();
        $originalSchema = (string) $database->query('SELECT current_schema()')->fetchColumn();
        $schema         = 'packio_audit_migration_' . bin2hex(random_bytes(8));
        $auditUuid      = 'legacy-audit-entry';

        $database->execute('CREATE SCHEMA ' . $schema);

        try {
            $database->execute('SET search_path TO ' . $schema);
            $database->execute('CREATE TABLE audit_entries (uuid varchar(255) PRIMARY KEY, occurred_at timestamp NOT NULL)');
            $database->execute('CREATE TABLE users (uuid varchar(255) PRIMARY KEY)');
            $database->execute('INSERT INTO audit_entries (uuid, occurred_at) VALUES (?, CURRENT_TIMESTAMP)', [$auditUuid]);

            // These statements are the PostgreSQL-safe staged data change in the
            // generated migration. Cycle's capsule always qualifies table names with
            // the configured public schema, so execute them against this isolated,
            // populated schema to verify the deployment behaviour itself.
            $database->execute("ALTER TABLE audit_entries ADD COLUMN subject_type varchar(255) NOT NULL DEFAULT 'legacy'");
            $database->execute("ALTER TABLE audit_entries ADD COLUMN subject_id varchar(255) NOT NULL DEFAULT ''");
            $database->execute("UPDATE audit_entries SET subject_id = uuid::text WHERE subject_id = ''");
            $database->execute('ALTER TABLE audit_entries ALTER COLUMN subject_type DROP DEFAULT');
            $database->execute('ALTER TABLE audit_entries ALTER COLUMN subject_id DROP DEFAULT');
            $database->execute('CREATE INDEX audit_entries_subject_recent_idx ON audit_entries (subject_type, subject_id, occurred_at, uuid)');

            $row = $database->query(
                'SELECT subject_type, subject_id FROM audit_entries WHERE uuid = ?',
                [$auditUuid],
            )->fetch();
            self::assertSame([
                'subject_type' => 'legacy',
                'subject_id'   => $auditUuid,
            ], $row);

            $columns = $database->query(
                "SELECT column_name, is_nullable, column_default FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'audit_entries' AND column_name IN ('subject_type', 'subject_id') ORDER BY column_name",
            )->fetchAll();
            self::assertSame([
                [
                    'column_name'    => 'subject_id',
                    'is_nullable'    => 'NO',
                    'column_default' => null,
                ],
                [
                    'column_name'    => 'subject_type',
                    'is_nullable'    => 'NO',
                    'column_default' => null,
                ],
            ], $columns);
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
