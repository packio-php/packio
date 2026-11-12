<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Facade;

use Packio\Organization\Api\Dto\OrganizationDeletionContext;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationOutcome;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationResult;
use Packio\Organization\Api\Dto\OrganizationInvitationDto;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Application\Command\OrganizationCommandService;
use Packio\Organization\Application\Command\OrganizationDeletionInitiator;
use Packio\Organization\Application\Port\OrganizationDeletionInitiationPort;
use Packio\Organization\Application\Port\OrganizationDeletionJobSchedulerPort;
use Packio\Organization\Application\Query\OrganizationQueryService;
use Packio\Organization\Application\Query\OrganizationSummariesReaderInterface;
use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Domain\Entity\OrganizationInvitation;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Shared\Api\Dto\PaginatedResultDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function array_map;
use function in_array;
use function trim;

final readonly class OrganizationApiFacade implements OrganizationApiFacadeInterface
{
    public function __construct(
        private OrganizationCommandService $organizationCommandService,
        private OrganizationQueryService $organizationQueryService,
        private ObjectMapperInterface $objectMapper,
        private OrganizationDeletionInitiator $organizationDeletionInitiator,
        private OrganizationDeletionInitiationPort $organizationDeletionInitiationPort,
        private OrganizationDeletionJobSchedulerPort $organizationDeletionJobSchedulerPort,
        private OrganizationSummariesReaderInterface $organizationSummariesReader,
    ) {}

    public function registerOrganization(string $name, ?string $ownerUserId = null): OrganizationDto
    {
        return $this->objectMapper->map(
            $this->organizationCommandService->registerOrganization($name, $ownerUserId),
            OrganizationDto::class,
        );
    }

    public function findOrganization(string $id): ?OrganizationDto
    {
        $organization = $this->organizationQueryService->findOrganization($id);

        return $organization instanceof Organization
            ? $this->objectMapper->map($organization, OrganizationDto::class)
            : null;
    }

    public function findOrganizationBySlug(string $slug): ?OrganizationDto
    {
        $organization = $this->organizationQueryService->findActiveOrganizationBySlug($slug);

        return $organization instanceof Organization
            ? $this->objectMapper->map($organization, OrganizationDto::class)
            : null;
    }

    /**
     * @return list<OrganizationDto>
     */
    public function allOrganizations(): array
    {
        return array_map(
            fn (Organization $organization): OrganizationDto => $this->objectMapper->map($organization, OrganizationDto::class),
            $this->organizationQueryService->allOrganizations(),
        );
    }

    public function organizationSummaries(?PaginationCriteria $paginationCriteria = null): PaginatedResultDto
    {
        return new PaginatedResultDto(...$this->organizationSummariesReader->fetch($paginationCriteria));
    }

    public function countOrganizations(): int
    {
        return $this->organizationQueryService->countOrganizations();
    }

    public function activeOrganizationsForUser(string $userId): array
    {
        return array_map(
            fn (Organization $organization): OrganizationDto => $this->objectMapper->map($organization, OrganizationDto::class),
            $this->organizationQueryService->activeOrganizationsForUser($userId),
        );
    }

    public function findMembership(string $organizationId, string $userId): ?OrganizationMemberDto
    {
        $membership = $this->organizationQueryService->findMembership($organizationId, $userId);

        return $membership instanceof OrganizationMember
            ? $this->objectMapper->map($membership, OrganizationMemberDto::class)
            : null;
    }

    /**
     * @return list<OrganizationMemberDto>
     */
    public function members(string $organizationId): array
    {
        return array_map(
            fn (OrganizationMember $organizationMember): OrganizationMemberDto => $this->objectMapper->map($organizationMember, OrganizationMemberDto::class),
            $this->organizationQueryService->members($organizationId),
        );
    }

    public function findMembersPaginated(string $organizationId, PaginationCriteria $paginationCriteria): PaginatedResultDto
    {
        $result = $this->organizationQueryService->findMembersPaginated($organizationId, $paginationCriteria);

        return new PaginatedResultDto(
            items: array_map(
                fn (OrganizationMember $organizationMember): OrganizationMemberDto => $this->objectMapper->map($organizationMember, OrganizationMemberDto::class),
                $result['items'],
            ),
            total: $result['total'],
            currentPage: $result['currentPage'],
            perPage: $result['perPage'],
        );
    }

    public function membersCount(string $organizationId): int
    {
        return $this->organizationQueryService->membersCount($organizationId);
    }

    public function addMember(string $organizationId, string $userId, string $role = 'member'): OrganizationMemberDto
    {
        return $this->objectMapper->map(
            $this->organizationCommandService->addMember($organizationId, $userId, $role),
            OrganizationMemberDto::class,
        );
    }

    public function removeMemberInContext(ActorContext $actorContext, string $memberId): bool
    {
        return $this->organizationCommandService->removeMemberInContext($actorContext, $memberId);
    }

    public function updateMemberRoleInContext(ActorContext $actorContext, string $memberId, string $role): ?OrganizationMemberDto
    {
        $membership = $this->organizationCommandService->updateMemberRoleInContext(
            $actorContext,
            $memberId,
            $role,
        );

        return $membership instanceof OrganizationMember
            ? $this->objectMapper->map($membership, OrganizationMemberDto::class)
            : null;
    }

    public function updateOrganization(string $organizationId, string $name, string $slug, bool $anonymousAccess): OrganizationDto
    {
        return $this->objectMapper->map(
            $this->organizationCommandService->updateOrganization(
                $organizationId,
                $name,
                $slug,
                $anonymousAccess,
            ),
            OrganizationDto::class,
        );
    }

    public function updateOrganizationSettingsInContext(
        ActorContext $actorContext,
        string $name,
        bool $anonymousAccess,
    ): OrganizationDto {
        return $this->objectMapper->map(
            $this->organizationCommandService->updateOrganizationSettingsInContext(
                $actorContext,
                $name,
                $anonymousAccess,
            ),
            OrganizationDto::class,
        );
    }

    /**
     * @return list<OrganizationInvitationDto>
     */
    public function invitations(string $organizationId): array
    {
        return array_map(
            fn (OrganizationInvitation $organizationInvitation): OrganizationInvitationDto => $this->objectMapper->map($organizationInvitation, OrganizationInvitationDto::class),
            $this->organizationQueryService->invitations($organizationId),
        );
    }

    public function invitationsCount(string $organizationId): int
    {
        return $this->organizationQueryService->invitationsCount($organizationId);
    }

    public function findInvitationByToken(string $token): ?OrganizationInvitationDto
    {
        $invitation = $this->organizationQueryService->findInvitationByToken($token);

        return $invitation instanceof OrganizationInvitation
            ? $this->objectMapper->map($invitation, OrganizationInvitationDto::class)
            : null;
    }

    public function createInvitationInContext(ActorContext $actorContext, string $role = 'member'): OrganizationInvitationDto
    {
        return $this->objectMapper->map(
            $this->organizationCommandService->createInvitationInContext($actorContext, $role),
            OrganizationInvitationDto::class,
        );
    }

    public function revokeInvitationInContext(ActorContext $actorContext, string $invitationId): bool
    {
        return $this->organizationCommandService->revokeInvitationInContext(
            $actorContext,
            $invitationId,
        );
    }

    public function deleteInvitationInContext(ActorContext $actorContext, string $invitationId): bool
    {
        return $this->organizationCommandService->deleteInvitationInContext(
            $actorContext,
            $invitationId,
        );
    }

    public function acceptInvitation(string $token, string $userId): ?OrganizationInvitationDto
    {
        $invitation = $this->organizationCommandService->acceptInvitation($token, $userId);

        return $invitation instanceof OrganizationInvitation
            ? $this->objectMapper->map($invitation, OrganizationInvitationDto::class)
            : null;
    }

    public function deleteOrganizationInContext(ActorContext $actorContext): bool
    {
        return in_array($this->deleteOrganizationInContextWithOutcome($actorContext)->outcome, [
            OrganizationDeletionInitiationOutcome::Initiated,
            OrganizationDeletionInitiationOutcome::AlreadyInProgress,
        ], true);
    }

    public function deleteOrganizationInContextWithOutcome(ActorContext $actorContext): OrganizationDeletionInitiationResult
    {
        if (
            '' === trim($actorContext->organizationId)
            || '' === trim($actorContext->actorId)
            || ! $actorContext->isOwner()
        ) {
            return new OrganizationDeletionInitiationResult(OrganizationDeletionInitiationOutcome::Rejected);
        }

        return $this->initiateOrganizationDeletion(new OrganizationDeletionContext($actorContext->organizationId, $actorContext->actorId));
    }

    public function createInvitation(string $organizationId, string $userId, string $role = 'member'): OrganizationInvitationDto
    {
        return $this->objectMapper->map(
            $this->organizationCommandService->createInvitation($organizationId, $userId, $role),
            OrganizationInvitationDto::class,
        );
    }

    public function revokeInvitation(string $organizationId, string $invitationId): bool
    {
        return $this->organizationCommandService->revokeInvitation($organizationId, $invitationId);
    }

    public function deleteInvitation(string $organizationId, string $invitationId): bool
    {
        return $this->organizationCommandService->deleteInvitation($organizationId, $invitationId);
    }

    /**
     * Atomically initiates staged organization deletion. The status
     * transition lives in the owning persistence boundary (row locked for
     * update), so concurrent or repeated initiations produce exactly one
     * `Initiated` outcome. The cleanup job is queued only after the
     * transition committed, so the worker can never observe a job before the
     * organization is `deleting`. If enqueue fails after the transition,
     * a retry reconstructs the exact durable lifecycle generation.
     */
    public function deleteOrganizationWithOutcome(
        string $organizationId,
        string $requestedByUserId = ''
    ): OrganizationDeletionInitiationResult {
        return $this->organizationDeletionInitiationPort->deleteOrganizationWithOutcome($organizationId, $requestedByUserId);
    }

    public function publishInitiationAfterAudit(string $organizationId, string $jobId): bool
    {
        return $this->organizationDeletionInitiationPort->publishInitiationAfterAudit($organizationId, $jobId);
    }

    public function restartDeletionStage(
        string $organizationId,
        string $stage,
        string $requestedByUserId = '',
    ): ?string {
        $resolvedStage = trim($stage);
        if (! in_array($resolvedStage, ['schedule_cleanup', 'finalize'], true)) {
            return null;
        }

        $deletionRunId = $this->organizationCommandService->ensureDeletionRun($organizationId);
        if (null === $deletionRunId) {
            return null;
        }

        return $this->organizationDeletionJobSchedulerPort->restartStage($organizationId, $deletionRunId, $resolvedStage, $requestedByUserId);
    }

    public function revertInitiation(
        string $organizationId,
        bool $previousActive,
        ?string $jobId = null,
        ?string $deletionRunId = null
    ): bool {
        return $this->organizationDeletionInitiationPort->revertInitiation($organizationId, $previousActive, $jobId, $deletionRunId);
    }

    public function initiateOrganizationDeletion(
        OrganizationDeletionContext $organizationDeletionContext
    ): OrganizationDeletionInitiationResult {
        return $this->organizationDeletionInitiator->initiate($organizationDeletionContext);
    }
}
