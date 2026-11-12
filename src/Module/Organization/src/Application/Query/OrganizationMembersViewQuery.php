<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Query;

use Packio\Organization\Application\Dto\OrganizationMembersView;
use Packio\Organization\Application\Port\UserDirectoryPort;
use Packio\Organization\Application\Security\OrganizationInvitationTokens;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Domain\Repository\OrganizationInvitationRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;

use function array_filter;
use function array_unique;
use function array_values;
use function count;

final readonly class OrganizationMembersViewQuery
{
    public function __construct(
        private OrganizationMemberRepositoryInterface $organizationMemberRepository,
        private OrganizationInvitationRepositoryInterface $organizationInvitationRepository,
        private UserDirectoryPort $userDirectoryPort,
        private OrganizationInvitationTokens $organizationInvitationTokens,
    ) {}

    public function fetch(string $organizationId, ?string $currentUserId, bool $canManageMembers): OrganizationMembersView
    {
        $memberships    = $this->organizationMemberRepository->findByOrganization($organizationId);
        $ownersCount    = count(
            array_filter(
                $memberships,
                static fn (OrganizationMember $organizationMember): bool => 'owner' === $organizationMember->role(),
            ),
        );

        $invitations = $this->organizationInvitationRepository->findByOrganization($organizationId);
        $userIds     = [];
        foreach ($memberships as $membership) {
            $userIds[] = $membership->userId();
        }

        foreach ($invitations as $invitation) {
            $userIds[] = $invitation->invitedByUserId();
        }
        $users = $this->userDirectoryPort->findByIds(array_values(array_unique($userIds)));

        $memberItems = [];
        foreach ($memberships as $membership) {
            $user          = $users[$membership->userId()] ?? null;
            $displayName   = $user['displayName'] ?? 'User';
            $email         = $user['email'] ?? 'unknown@example.com';
            $isCurrentUser = null !== $currentUserId && $membership->userId() === $currentUserId;
            $isLastOwner   = 'owner' === $membership->role() && $ownersCount <= 1;
            $canPromote    = $canManageMembers && ! $isCurrentUser && 'member' === $membership->role();
            $canDemote     = $canManageMembers && ! $isCurrentUser && 'owner' === $membership->role() && ! $isLastOwner;

            $memberItems[] = [
                'id'              => $membership->getIdentifier()->toString(),
                'display_name'    => $displayName,
                'email'           => $email,
                'role'            => $membership->role(),
                'is_current_user' => $isCurrentUser,
                'can_remove'      => $canManageMembers && ! $isCurrentUser && ! $isLastOwner,
                'can_update_role' => $canPromote || $canDemote,
                'can_promote'     => $canPromote,
                'can_demote'      => $canDemote,
                'created_at'      => $membership->getCreatedAt()->format(DATE_ATOM),
            ];
        }

        $invitationItems = [];
        foreach ($invitations as $invitation) {
            $inviter   = $users[$invitation->invitedByUserId()] ?? null;
            $invitedBy = $inviter['displayName'] ?? $inviter['email'] ?? 'Unknown';

            $invitationItems[] = [
                'id'         => $invitation->getIdentifier()->toString(),
                'role'       => $invitation->role(),
                'status'     => $invitation->status(),
                'invited_by' => $invitedBy,
                'token'      => $this->organizationInvitationTokens->decrypt($invitation->token()),
                'expires_at' => $invitation->expiresAt()?->format(DATE_ATOM) ?? '',
            ];
        }

        return new OrganizationMembersView($memberItems, $invitationItems, $ownersCount, $canManageMembers);
    }
}
