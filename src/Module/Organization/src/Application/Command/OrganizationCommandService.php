<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Command;

use Packio\Organization\Api\Exception\OrganizationMembershipException;
use Packio\Organization\Api\Exception\OrganizationNameException;
use Packio\Organization\Api\Exception\OrganizationNotFoundException;
use Packio\Organization\Api\Exception\OrganizationSlugException;
use Packio\Organization\Application\Config\ReservedOrganizationSlugs;
use Packio\Organization\Application\Security\OrganizationInvitationTokens;
use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Domain\Entity\OrganizationInvitation;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Domain\Repository\OrganizationInvitationRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationRepositoryInterface;
use Packio\Organization\Domain\Value\OrganizationDeletionInitiation;
use Packio\Shared\Domain\Organization\ActorContext;
use RuntimeException;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;

use function in_array;
use function strlen;
use function trim;

final readonly class OrganizationCommandService
{
    private const int MAX_ORGANIZATION_NAME_LENGTH = 50;

    public function __construct(
        private OrganizationRepositoryInterface $organizationRepository,
        private OrganizationInvitationRepositoryInterface $organizationInvitationRepository,
        private OrganizationMemberRepositoryInterface $organizationMemberRepository,
        private ReservedOrganizationSlugs $reservedOrganizationSlugs,
        private OrganizationInvitationTokens $organizationInvitationTokens,
    ) {}

    public function registerOrganization(string $name, ?string $ownerUserId = null): Organization
    {
        $normalizedName = $this->normalizeAndValidateOrganizationName($name);

        $organization = Organization::register(
            $normalizedName,
            $normalizedName,
        );

        $this->assertOrganizationNameAvailable($organization->name());

        $existing = $this->organizationRepository->findBySlug($organization->slug());
        if ($existing instanceof Organization) {
            throw OrganizationSlugException::alreadyTaken();
        }

        $organization = $this->organizationRepository->save($organization);

        if (null !== $ownerUserId && '' !== trim($ownerUserId)) {
            $this->ensureMembership(
                $organization->getIdentifier()->toString(),
                $ownerUserId,
                'owner',
            );
        }

        return $organization;
    }

    public function addMember(string $organizationId, string $userId, string $role = 'member'): OrganizationMember
    {
        return $this->ensureMembership($organizationId, $userId, $role);
    }

    public function removeMember(string $organizationId, string $memberId): bool
    {
        $membership = $this->organizationMemberRepository->findById($memberId);
        if (! $membership instanceof OrganizationMember) {
            return false;
        }

        if ($membership->organizationId() !== $organizationId) {
            return false;
        }

        if ('owner' === $membership->role()) {
            if (! $this->organizationMemberRepository->deleteOwnerWhenAnotherOwnerExists($organizationId, $memberId)) {
                throw OrganizationMembershipException::lastOwnerCannotBeRemoved();
            }

            return true;
        }

        $this->organizationMemberRepository->deleteAndFlush($membership);

        return true;
    }

    public function removeMemberInContext(ActorContext $actorContext, string $memberId): bool
    {
        $this->assertOwnerContext($actorContext);

        $membership = $this->organizationMemberRepository->findById($memberId);
        if (! $membership instanceof OrganizationMember) {
            return false;
        }

        if ($membership->organizationId() !== $actorContext->organizationId) {
            return false;
        }

        if ($membership->userId() === $actorContext->actorId) {
            throw OrganizationMembershipException::selfRemove();
        }

        return $this->removeMember($actorContext->organizationId, $memberId);
    }

    public function updateMemberRole(string $organizationId, string $memberId, string $role): ?OrganizationMember
    {
        $membership = $this->organizationMemberRepository->findById($memberId);
        if (! $membership instanceof OrganizationMember) {
            return null;
        }

        if ($membership->organizationId() !== $organizationId) {
            return null;
        }

        $normalizedRole = trim($role);
        if (! in_array($normalizedRole, ['member', 'owner'], true)) {
            throw OrganizationMembershipException::invalidRole();
        }

        if ($membership->role() === $normalizedRole) {
            return $membership;
        }

        if ('owner' === $normalizedRole) {
            $membership->promoteToOwner();

            return $this->organizationMemberRepository->save($membership);
        }

        if (! $this->organizationMemberRepository->demoteOwnerToMemberWhenAnotherOwnerExists($organizationId, $memberId)) {
            throw OrganizationMembershipException::lastOwnerCannotBeDemoted();
        }

        $membership->demoteToMember();

        return $membership;
    }

    public function updateMemberRoleInContext(ActorContext $actorContext, string $memberId, string $role): ?OrganizationMember
    {
        $this->assertOwnerContext($actorContext);

        $membership = $this->organizationMemberRepository->findById($memberId);
        if (! $membership instanceof OrganizationMember) {
            return null;
        }

        if ($membership->organizationId() !== $actorContext->organizationId) {
            return null;
        }

        if ($membership->userId() === $actorContext->actorId) {
            throw OrganizationMembershipException::selfRoleChange();
        }

        return $this->updateMemberRole($actorContext->organizationId, $memberId, $role);
    }

    public function updateOrganization(string $organizationId, string $name, string $slug, bool $anonymousAccess): Organization
    {
        $organization = $this->organizationRepository->findById($organizationId);
        if (! $organization instanceof Organization) {
            throw new OrganizationNotFoundException();
        }

        $normalizedName = $this->normalizeAndValidateOrganizationName($name);
        $normalizedSlug = trim($slug);

        if ('' === $normalizedSlug) {
            throw OrganizationSlugException::required();
        }

        $this->assertOrganizationSlugAllowed($normalizedSlug);

        if ($normalizedSlug !== $organization->slug()) {
            throw OrganizationSlugException::immutable();
        }

        $this->renameOrganization($organization, $normalizedName);
        $organization->updateAnonymousAccess($anonymousAccess);

        return $this->organizationRepository->save($organization);
    }

    public function updateOrganizationSettingsInContext(
        ActorContext $actorContext,
        string $name,
        bool $anonymousAccess,
    ): Organization {
        $this->assertOwnerContext($actorContext);

        $organization = $this->organizationRepository->findById($actorContext->organizationId);
        if (! $organization instanceof Organization) {
            throw new OrganizationNotFoundException();
        }

        $this->renameOrganization(
            $organization,
            $this->normalizeAndValidateOrganizationName($name),
        );
        $organization->updateAnonymousAccess($anonymousAccess);

        return $this->organizationRepository->save($organization);
    }

    public function initiateDeletion(string $organizationId): OrganizationDeletionInitiation
    {
        return $this->organizationRepository->initiateDeletion($organizationId);
    }

    public function ensureDeletionRun(string $organizationId): ?string
    {
        return $this->organizationRepository->ensureDeletionRun($organizationId);
    }

    public function revertInitiation(string $organizationId, bool $previousActive, string $deletionRunId): void
    {
        $this->organizationRepository->revertInitiation($organizationId, $previousActive, $deletionRunId);
    }

    public function createInvitation(string $organizationId, string $invitedByUserId, string $role = 'member'): OrganizationInvitation
    {
        $organizationInvitation = OrganizationInvitation::invite($organizationId, $invitedByUserId, $role);
        $organizationInvitation->storeToken($this->organizationInvitationTokens->encrypt($organizationInvitation->token()));

        return $this->organizationInvitationRepository->save($organizationInvitation);
    }

    public function createInvitationInContext(ActorContext $actorContext, string $role = 'member'): OrganizationInvitation
    {
        $this->assertOwnerContext($actorContext);

        return $this->createInvitation(
            $actorContext->organizationId,
            $actorContext->actorId,
            $role,
        );
    }

    public function revokeInvitation(string $organizationId, string $invitationId): bool
    {
        $invitation = $this->organizationInvitationRepository->findById($invitationId);
        if (! $invitation instanceof OrganizationInvitation) {
            return false;
        }

        if ($invitation->organizationId() !== $organizationId) {
            return false;
        }

        $invitation->revoke();
        $this->organizationInvitationRepository->save($invitation);

        return true;
    }

    public function revokeInvitationInContext(ActorContext $actorContext, string $invitationId): bool
    {
        $this->assertOwnerContext($actorContext);

        return $this->revokeInvitation($actorContext->organizationId, $invitationId);
    }

    public function deleteInvitation(string $organizationId, string $invitationId): bool
    {
        $invitation = $this->organizationInvitationRepository->findById($invitationId);
        if (! $invitation instanceof OrganizationInvitation) {
            return false;
        }

        if ($invitation->organizationId() !== $organizationId) {
            return false;
        }

        if ($invitation->isPending()) {
            return false;
        }

        $this->organizationInvitationRepository->deleteAndFlush($invitation);

        return true;
    }

    public function deleteInvitationInContext(ActorContext $actorContext, string $invitationId): bool
    {
        $this->assertOwnerContext($actorContext);

        return $this->deleteInvitation($actorContext->organizationId, $invitationId);
    }

    public function acceptInvitation(string $token, string $userId): ?OrganizationInvitation
    {
        $invitation = $this->organizationInvitationRepository->findByToken(trim($token));
        if (! $invitation instanceof OrganizationInvitation) {
            return null;
        }

        if (! $invitation->isPending()) {
            return $invitation;
        }

        // An existing member keeps their role; an invitation must not promote them.
        $isMember = $this->organizationMemberRepository->findByOrganizationAndUser(
            $invitation->organizationId(),
            $userId,
        ) instanceof OrganizationMember;
        if (! $isMember) {
            $this->ensureMembership(
                $invitation->organizationId(),
                $userId,
                $invitation->role(),
            );
        }

        $invitation->accept($userId);

        return $this->organizationInvitationRepository->save($invitation);
    }

    private function ensureMembership(string $organizationId, string $userId, string $role): OrganizationMember
    {
        $existing = $this->organizationMemberRepository->findByOrganizationAndUser(
            $organizationId,
            $userId,
        );

        if ($existing instanceof OrganizationMember) {
            if ('owner' === $role) {
                $existing->promoteToOwner();
                $existing = $this->organizationMemberRepository->save($existing);
            }

            return $existing;
        }

        try {
            return $this->organizationMemberRepository->save(
                OrganizationMember::join($organizationId, $userId, $role),
            );
        } catch (RepositoryPersistException $repositoryPersistException) {
            // A concurrent request inserted the same membership first.
            $concurrent = $this->organizationMemberRepository->findByOrganizationAndUser($organizationId, $userId);
            if ($concurrent instanceof OrganizationMember) {
                return $concurrent;
            }

            throw $repositoryPersistException;
        }
    }

    private function assertOwnerContext(ActorContext $actorContext): void
    {
        if (
            '' === trim($actorContext->organizationId)
            || '' === trim($actorContext->actorId)
            || ! $actorContext->isOwner()
        ) {
            throw new RuntimeException('Owner actor context is required.');
        }
    }

    private function normalizeAndValidateOrganizationName(string $name): string
    {
        $normalizedName = Organization::normalizeName($name);

        if ('' === $normalizedName) {
            throw OrganizationNameException::required();
        }

        if (strlen($normalizedName) > self::MAX_ORGANIZATION_NAME_LENGTH) {
            throw OrganizationNameException::tooLong();
        }

        $this->assertOrganizationNameAllowed($normalizedName);

        return $normalizedName;
    }

    private function renameOrganization(Organization $organization, string $name): void
    {
        $this->assertOrganizationNameAvailable(
            $name,
            $organization->getIdentifier()->toString(),
        );

        $organization->rename($name);
    }

    private function assertOrganizationNameAvailable(string $name, ?string $currentOrganizationId = null): void
    {
        if ($this->organizationRepository->existsWithNameKey(Organization::normalizeNameForComparison($name), $currentOrganizationId)) {
            throw OrganizationNameException::alreadyTaken();
        }
    }

    private function assertOrganizationNameAllowed(string $name): void
    {
        if ($this->reservedOrganizationSlugs->contains($name)) {
            throw OrganizationNameException::reserved();
        }
    }

    private function assertOrganizationSlugAllowed(string $slug): void
    {
        if ($this->reservedOrganizationSlugs->contains($slug)) {
            throw OrganizationSlugException::reserved();
        }
    }
}
