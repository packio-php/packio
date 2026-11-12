<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\Migrations\Capsule;
use Migration\OrmDefaultF731196e8b9f8db06af9ef57310d509a;
use PackioTest\Support\Base\IntegrationTestCase;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

use function bin2hex;
use function random_bytes;

require_once __DIR__ . '/../../../../../../data/db/cycle/migrations/20261024.223059_0_0_default_change_organization_invitations_add_index_organization_invitations_token_unique_add_index_organization_invitations_org.php';

final class OrganizationMembershipDeduplicationMigrationTest extends IntegrationTestCase
{
    #[Test]
    public function itKeepsTheOwnerRowAndRecreatesTheUniqueIndex(): void
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $database = $databaseProvider->database();

        foreach ([
            'organization_members_organization_user_unique',
            'organization_members_user_id_idx',
            'organization_invitations_organization_id_idx',
        ] as $index) {
            $database->execute('DROP INDEX ' . $index);
        }

        $organizationId = 'org-' . bin2hex(random_bytes(6));
        $ownerUuid      = Uuid::uuid7()->toString();
        foreach ([
            [Uuid::uuid7()->toString(), 'member', '2026-01-01 00:00:00'],
            [$ownerUuid, 'owner', '2026-01-02 00:00:00'],
            [Uuid::uuid7()->toString(), 'member', '2026-01-03 00:00:00'],
        ] as [$uuid, $role, $createdAt]) {
            $database->execute(
                'INSERT INTO organization_members (uuid, organization_id, user_id, role, created_at) VALUES (?, ?, ?, ?, ?)',
                [$uuid, $organizationId, 'user-dup', $role, $createdAt],
            );
        }

        new OrmDefaultF731196e8b9f8db06af9ef57310d509a()
            ->withCapsule(new Capsule($database))
            ->up()
        ;
        // A later migration replaced this index with organization_invitations_token_hash_unique.
        $database->execute('DROP INDEX organization_invitations_token_unique');

        self::assertSame(
            [$ownerUuid],
            $database->query('SELECT uuid FROM organization_members WHERE organization_id = ?', [$organizationId])->fetchAll(PDO::FETCH_COLUMN),
        );
        self::assertSame(
            1,
            $database->query("SELECT count(*) FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'organization_members_organization_user_unique'")->fetchColumn(),
        );
    }
}
