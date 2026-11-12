<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;
use Ramsey\Uuid\Uuid;

use function is_string;

/**
 * Cycle schema cannot express this PostgreSQL JSON payload data migration.
 * Bring deleting organizations created before deletion_run_id under the same
 * generation guard as newly initiated lifecycles, including leased workers.
 */
final class BackfillOrganizationDeletionRunIds extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $database      = $this->database();
        $organizations = $database->select('uuid')
            ->from('organizations')
            ->where('status', 'deleting')
            ->where('deletion_run_id', null)
            ->fetchAll()
        ;

        foreach ($organizations as $organization) {
            $organizationId = $organization['uuid'] ?? null;
            if (! is_string($organizationId) || '' === $organizationId) {
                continue;
            }

            $deletionRunId = $database->query(
                <<<'SQL'
                    SELECT payload->>'deletion_run_id'
                    FROM jobs
                    WHERE type = 'organization.delete'
                      AND organization_id = :organization_id
                      AND status IN ('held', 'audited', 'pending', 'running', 'failed')
                      AND COALESCE(payload->>'deletion_run_id', '') <> ''
                    ORDER BY
                      CASE status
                        WHEN 'running' THEN 0
                        WHEN 'pending' THEN 1
                        WHEN 'audited' THEN 2
                        WHEN 'held' THEN 3
                        ELSE 4
                      END,
                      created_at DESC,
                      uuid DESC
                    LIMIT 1
                    SQL,
                [
                    'organization_id' => $organizationId,
                ],
            )->fetchColumn();
            if (! is_string($deletionRunId) || '' === $deletionRunId) {
                $deletionRunId = Uuid::uuid7()->toString();
            }
            $database->update('organizations')->values([
                'deletion_run_id' => $deletionRunId,
            ])->where('uuid', $organizationId)
                ->where('deletion_run_id', null)
                ->run()
            ;

            $database->execute(
                <<<'SQL'
                    UPDATE jobs
                    SET payload = jsonb_set(payload::jsonb, '{deletion_run_id}', to_jsonb(CAST(:deletion_run_id AS text)), true)::json
                    WHERE type = 'organization.delete'
                      AND status IN ('held', 'audited', 'pending', 'running', 'failed')
                      AND organization_id = :organization_id
                      AND payload->>'deletion_run_id' IS NULL
                    SQL,
                [
                    'deletion_run_id'  => $deletionRunId,
                    'organization_id'  => $organizationId,
                ],
            );
        }
    }

    public function down(): void
    {
        // A generated lifecycle run is durable state and cannot be safely removed.
    }
}
