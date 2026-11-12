<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;
use Ramsey\Uuid\Uuid;

use function is_string;

/**
 * Cycle schema cannot express this PostgreSQL JSON payload data migration.
 * Backfill the persisted generation first, then attach it to unprocessed
 * legacy source.delete jobs so the lifecycle guard can execute them safely.
 */
final class BackfillSourceDeletionRunIds extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $database = $this->database();
        $sources  = $database->select('uuid')
            ->from('sources')
            ->where('status', 'deleting')
            ->where('deletion_run_id', null)
            ->fetchAll()
        ;

        foreach ($sources as $source) {
            $sourceId = $source['uuid'] ?? null;
            if (! is_string($sourceId) || '' === $sourceId) {
                continue;
            }

            $deletionRunId = Uuid::uuid7()->toString();
            $database->update('sources')->values([
                'deletion_run_id' => $deletionRunId,
            ])->where('uuid', $sourceId)
                ->where('deletion_run_id', null)
                ->run()
            ;

            $database->execute(
                <<<'SQL'
                    UPDATE jobs
                    SET payload = jsonb_set(payload::jsonb, '{deletion_run_id}', to_jsonb(CAST(:deletion_run_id AS text)), true)::json
                    WHERE type = 'source.delete'
                      AND status IN ('held', 'audited', 'pending', 'running', 'failed')
                      AND payload->>'source_id' = :source_id
                      AND payload->>'deletion_run_id' IS NULL
                    SQL,
                [
                    'deletion_run_id' => $deletionRunId,
                    'source_id'       => $sourceId,
                ],
            );
        }
    }

    public function down(): void
    {
        // A generated lifecycle run is durable state and cannot be safely removed.
    }
}
