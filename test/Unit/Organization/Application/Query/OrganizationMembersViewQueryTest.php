<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Application\Query;

use Cake\Chronos\Chronos;
use Common\Security\SodiumSecretEncryptor;
use Packio\Organization\Application\Port\UserDirectoryPort;
use Packio\Organization\Application\Query\OrganizationMembersViewQuery;
use Packio\Organization\Application\Security\OrganizationInvitationTokens;
use Packio\Organization\Domain\Entity\OrganizationInvitation;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Domain\Repository\OrganizationInvitationRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

use function count;

final class OrganizationMembersViewQueryTest extends TestCase
{
    #[Test]
    #[DataProvider('permissions')]
    public function itBuildsPermissionsAndMissingUserFallbacks(
        bool $canManage,
        ?string $actor,
        string $role,
        int $owners,
        bool $canRemove,
        bool $canPromote,
        bool $canDemote
    ): void {
        $membership = new OrganizationMember('org', 'target', $role);
        $membership->setIdentifier(Uuid::uuid7());
        $membership->setCreatedAt(new Chronos('2026-01-01'));
        $memberships = [$membership];
        if ($owners > ('owner' === $role ? 1 : 0)) {
            $owner = new OrganizationMember('org', 'other', 'owner');
            $owner->setIdentifier(Uuid::uuid7());
            $owner->setCreatedAt(new Chronos('2026-01-01'));
            $memberships[] = $owner;
        }
        $organizationInvitation = new OrganizationInvitation('org', 'target', 'token/value', expiresAt: new Chronos('2099-01-01'));
        $organizationInvitation->setIdentifier(Uuid::uuid7());
        $members = $this->createMock(OrganizationMemberRepositoryInterface::class);
        $members->expects(self::once())->method('findByOrganization')->with('org')->willReturn($memberships);
        $invitations = $this->createMock(OrganizationInvitationRepositoryInterface::class);
        $invitations->expects(self::once())->method('findByOrganization')->with('org')->willReturn([$organizationInvitation]);
        $directory = $this->createMock(UserDirectoryPort::class);
        $directory->expects(self::once())->method('findByIds')->with(1 === count($memberships) ? ['target'] : ['target', 'other'])->willReturn([
            'other' => [
                'displayName' => 'Other owner',
                'email'       => 'other@example.test',
            ],
        ]);
        $organizationMembersView = new OrganizationMembersViewQuery($members, $invitations, $directory, new OrganizationInvitationTokens(new SodiumSecretEncryptor('test-key')))->fetch('org', $actor, $canManage);
        self::assertSame($owners, $organizationMembersView->ownersCount);
        self::assertSame($canManage, $organizationMembersView->canManageMembers);
        self::assertSame('User', $organizationMembersView->members[0]['display_name']);
        self::assertSame('unknown@example.com', $organizationMembersView->members[0]['email']);
        self::assertSame('target' === $actor, $organizationMembersView->members[0]['is_current_user']);
        self::assertSame($canRemove, $organizationMembersView->members[0]['can_remove']);
        self::assertSame($canPromote, $organizationMembersView->members[0]['can_promote']);
        self::assertSame($canDemote, $organizationMembersView->members[0]['can_demote']);
        self::assertSame($canPromote || $canDemote, $organizationMembersView->members[0]['can_update_role']);
        self::assertSame('Unknown', $organizationMembersView->invitations[0]['invited_by']);
        self::assertSame('token/value', $organizationMembersView->invitations[0]['token']);
        self::assertSame('pending', $organizationMembersView->invitations[0]['status']);
        if (2 === count($memberships)) {
            self::assertSame('Other owner', $organizationMembersView->members[1]['display_name']);
            self::assertSame('other@example.test', $organizationMembersView->members[1]['email']);
        }
    }

    /** @return iterable<string, array{bool, ?string, string, int, bool, bool, bool}> */
    public static function permissions(): iterable
    {
        yield 'promote member' => [true, 'actor', 'member', 1, true, true, false];

        yield 'demote second owner' => [true, 'actor', 'owner', 2, true, false, true];

        yield 'last owner' => [true, 'actor', 'owner', 1, false, false, false];

        yield 'self member' => [true, 'target', 'member', 1, false, false, false];

        yield 'self owner' => [true, 'target', 'owner', 2, false, false, false];

        yield 'read only' => [false, 'actor', 'member', 1, false, false, false];

        yield 'no actor' => [false, null, 'owner', 2, false, false, false];
    }

    #[Test]
    public function itLoadsInvitersWhoAreNotMembersInTheSameBatch(): void
    {
        $organizationMember = new OrganizationMember('org', 'member');
        $organizationMember->setIdentifier(Uuid::uuid7());
        $organizationMember->setCreatedAt(new Chronos('2026-01-01'));
        $organizationInvitation = new OrganizationInvitation('org', 'former-member', 'invite-token');
        $organizationInvitation->setIdentifier(Uuid::uuid7());
        $members = $this->createMock(OrganizationMemberRepositoryInterface::class);
        $members->expects(self::once())->method('findByOrganization')->with('org')->willReturn([$organizationMember]);
        $invitations = $this->createMock(OrganizationInvitationRepositoryInterface::class);
        $invitations->expects(self::once())->method('findByOrganization')->with('org')->willReturn([$organizationInvitation, $organizationInvitation]);
        $directory = $this->createMock(UserDirectoryPort::class);
        $directory->expects(self::once())->method('findByIds')->with(['member', 'former-member'])->willReturn([
            'member'        => [
                'displayName' => 'Member',
                'email'       => 'member@example.test',
            ],
            'former-member' => [
                'displayName' => 'Inviter',
                'email'       => 'inviter@example.test',
            ],
        ]);
        $organizationMembersView = new OrganizationMembersViewQuery($members, $invitations, $directory, new OrganizationInvitationTokens(new SodiumSecretEncryptor('test-key')))->fetch('org', 'member', false);
        self::assertSame('Member', $organizationMembersView->members[0]['display_name']);
        self::assertSame('Inviter', $organizationMembersView->invitations[0]['invited_by']);
        self::assertSame('Inviter', $organizationMembersView->invitations[1]['invited_by']);
    }

    #[Test]
    public function emptyOrganization(): void
    {
        $members = $this->createMock(OrganizationMemberRepositoryInterface::class);
        $members->expects(self::once())->method('findByOrganization')->willReturn([]);
        $invitations = $this->createMock(OrganizationInvitationRepositoryInterface::class);
        $invitations->expects(self::once())->method('findByOrganization')->willReturn([]);
        $directory = $this->createMock(UserDirectoryPort::class);
        $directory->expects(self::once())->method('findByIds')->with([])->willReturn([]);
        $organizationMembersView = new OrganizationMembersViewQuery($members, $invitations, $directory, new OrganizationInvitationTokens(new SodiumSecretEncryptor('test-key')))->fetch('org', null, false);
        self::assertSame([], $organizationMembersView->members);
        self::assertSame([], $organizationMembersView->invitations);
        self::assertSame(0, $organizationMembersView->ownersCount);
    }
}
