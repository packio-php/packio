<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\Migrations\Capsule;
use Migration\OrmDefault3bfe03b54d2fb8317e2c12bbc56e1790;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function hash;
use function random_bytes;

require_once __DIR__ . '/../../../../../../data/db/cycle/migrations/20261110.204555_0_0_default_change_organization_invitations_add_token_hash_add_index_organization_invitations_token_hash_unique.php';

final class OrganizationInvitationTokenHashMigrationTest extends IntegrationTestCase
{
    #[Test]
    public function itBackfillsTheHashOfALegacyPlaintextTokenSoTheLinkStillWorks(): void
    {
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizations);
        $organizationDto             = $organizations->registerOrganization('invite-backfill-' . bin2hex(random_bytes(4)), 'inviter-id');
        $organizationInvitationDto   = $organizations->createInvitation($organizationDto->id, 'inviter-id');

        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $database  = $databaseProvider->database();
        $migration = new OrmDefault3bfe03b54d2fb8317e2c12bbc56e1790()->withCapsule(new Capsule($database));

        $migration->down();
        $legacyToken = 'orgi_legacy' . bin2hex(random_bytes(12));
        $database->execute('UPDATE organization_invitations SET token = ? WHERE uuid = ?', [$legacyToken, $organizationInvitationDto->id]);
        $migration->up();

        self::assertSame(
            hash('sha256', $legacyToken),
            $database->query('SELECT token_hash FROM organization_invitations WHERE uuid = ?', [$organizationInvitationDto->id])->fetchColumn(),
        );

        $this->rebuildContainer();
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizations);
        self::assertSame($organizationInvitationDto->id, $organizations->findInvitationByToken($legacyToken)?->id);
    }
}
