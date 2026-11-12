<?php

declare(strict_types=1);

namespace Packio\Organization\Api;

use Packio\Organization\Api\Dto\OrganizationDeletionContext;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationResult;
use Packio\Organization\Api\Dto\OrganizationInvitationDto;
use Packio\Organization\Api\Dto\OrganizationSummaryDto;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Shared\Api\Dto\PaginatedResultDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Shared\Domain\Pagination\PaginationCriteria;

interface OrganizationApiFacadeInterface
{
    public function initiateOrganizationDeletion(
        OrganizationDeletionContext $organizationDeletionContext
    ): OrganizationDeletionInitiationResult;

    /** @return PaginatedResultDto<OrganizationSummaryDto> */
    public function organizationSummaries(?PaginationCriteria $paginationCriteria = null): PaginatedResultDto;

    public function countOrganizations(): int;

    public function registerOrganization(string $name, ?string $ownerUserId = null): OrganizationDto;

    public function findOrganization(string $id): ?OrganizationDto;

    public function findOrganizationBySlug(string $slug): ?OrganizationDto;

    /**
     * @return list<OrganizationDto>
     */
    public function allOrganizations(): array;

    /**
     * @return list<OrganizationDto>
     */
    public function activeOrganizationsForUser(string $userId): array;

    public function findMembership(string $organizationId, string $userId): ?OrganizationMemberDto;

    /**
     * @return list<OrganizationMemberDto>
     */
    public function members(string $organizationId): array;

    /**
     * @phpstan-return PaginatedResultDto<OrganizationMemberDto>
     */
    public function findMembersPaginated(string $organizationId, PaginationCriteria $paginationCriteria): PaginatedResultDto;

    public function membersCount(string $organizationId): int;

    public function addMember(string $organizationId, string $userId, string $role = 'member'): OrganizationMemberDto;

    public function removeMemberInContext(ActorContext $actorContext, string $memberId): bool;

    public function updateMemberRoleInContext(ActorContext $actorContext, string $memberId, string $role): ?OrganizationMemberDto;

    public function updateOrganization(string $organizationId, string $name, string $slug, bool $anonymousAccess): OrganizationDto;

    public function updateOrganizationSettingsInContext(
        ActorContext $actorContext,
        string $name,
        bool $anonymousAccess,
    ): OrganizationDto;

    /**
     * @return list<OrganizationInvitationDto>
     */
    public function invitations(string $organizationId): array;

    public function invitationsCount(string $organizationId): int;

    public function findInvitationByToken(string $token): ?OrganizationInvitationDto;

    public function createInvitationInContext(ActorContext $actorContext, string $role = 'member'): OrganizationInvitationDto;

    public function revokeInvitationInContext(ActorContext $actorContext, string $invitationId): bool;

    public function deleteInvitationInContext(ActorContext $actorContext, string $invitationId): bool;

    public function acceptInvitation(string $token, string $userId): ?OrganizationInvitationDto;

    public function deleteOrganizationInContext(ActorContext $actorContext): bool;

    public function deleteOrganizationInContextWithOutcome(ActorContext $actorContext): OrganizationDeletionInitiationResult;

    public function createInvitation(string $organizationId, string $userId, string $role = 'member'): OrganizationInvitationDto;

    public function revokeInvitation(string $organizationId, string $invitationId): bool;

    public function deleteInvitation(string $organizationId, string $invitationId): bool;

    /**
     * Atomically initiates staged organization deletion. The status
     * transition lives in the owning persistence boundary (row locked for
     * update), so concurrent or repeated initiations produce exactly one
     * `Initiated` outcome. The cleanup job stays held until its caller
     * publishes it after audit recording succeeds.
     */
    public function deleteOrganizationWithOutcome(
        string $organizationId,
        string $requestedByUserId = '',
    ): OrganizationDeletionInitiationResult;

    /** Advances a lifecycle only after its durable audit operation exists. */
    public function publishInitiationAfterAudit(string $organizationId, string $jobId): bool;

    /** Restarts one current deletion stage through the owning scheduler; returns the active or new job id. */
    public function restartDeletionStage(
        string $organizationId,
        string $stage,
        string $requestedByUserId = '',
    ): ?string;

    /**
     * Compensating revert restores the organization only after the exact held
     * lifecycle job was canceled successfully.
     */
    public function revertInitiation(
        string $organizationId,
        bool $previousActive,
        ?string $jobId = null,
        ?string $deletionRunId = null,
    ): bool;
}
