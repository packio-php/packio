<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Query;

use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Domain\Entity\OrganizationInvitation;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Domain\Repository\OrganizationInvitationRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationRepositoryInterface;
use Packio\Shared\Domain\Pagination\PaginationCriteria;

use function array_filter;
use function array_map;
use function array_values;
use function trim;

final readonly class OrganizationQueryService
{
    public function __construct(
        private OrganizationRepositoryInterface $organizationRepository,
        private OrganizationInvitationRepositoryInterface $organizationInvitationRepository,
        private OrganizationMemberRepositoryInterface $organizationMemberRepository,
    ) {}

    public function findOrganization(string $id): ?Organization
    {
        return $this->organizationRepository->findById($id);
    }

    public function findActiveOrganizationBySlug(string $slug): ?Organization
    {
        $organization = $this->organizationRepository->findBySlug($slug);

        return $organization instanceof Organization && $organization->isActive()
            ? $organization
            : null;
    }

    /**
     * @return list<Organization>
     */
    public function allOrganizations(): array
    {
        return $this->organizationRepository->findAll();
    }

    public function countOrganizations(): int
    {
        return $this->organizationRepository->countAll();
    }

    /**
     * @return list<Organization>
     */
    public function activeOrganizationsForUser(string $userId): array
    {
        $organizationIds = array_map(
            static fn (OrganizationMember $organizationMember): string => $organizationMember->organizationId(),
            $this->organizationMemberRepository->findByUserId($userId),
        );

        return array_values(
            array_filter(
                $this->organizationRepository->findByIds($organizationIds),
                static fn (Organization $organization): bool => $organization->isActive(),
            ),
        );
    }

    public function findMembership(string $organizationId, string $userId): ?OrganizationMember
    {
        return $this->organizationMemberRepository->findByOrganizationAndUser(
            $organizationId,
            $userId,
        );
    }

    /**
     * @return list<OrganizationMember>
     */
    public function members(string $organizationId): array
    {
        return $this->organizationMemberRepository->findByOrganization($organizationId);
    }

    /**
     * @return array{items: list<OrganizationMember>, total: int, currentPage: int, perPage: int}
     */
    public function findMembersPaginated(string $organizationId, PaginationCriteria $paginationCriteria): array
    {
        return $this->organizationMemberRepository->findPaginatedByOrganization($organizationId, $paginationCriteria);
    }

    public function membersCount(string $organizationId): int
    {
        return $this->organizationMemberRepository->countByOrganization($organizationId);
    }

    /**
     * @return list<OrganizationInvitation>
     */
    public function invitations(string $organizationId): array
    {
        return $this->organizationInvitationRepository->findByOrganization($organizationId);
    }

    public function invitationsCount(string $organizationId): int
    {
        return $this->organizationInvitationRepository->countByOrganization($organizationId);
    }

    public function findInvitationByToken(string $token): ?OrganizationInvitation
    {
        return $this->organizationInvitationRepository->findByToken(trim($token));
    }
}
