<?php

declare(strict_types=1);

namespace Packio\Organization\Domain\Repository;

use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

interface OrganizationMemberRepositoryInterface
{
    public function save(OrganizationMember $organizationMember): OrganizationMember;

    public function demoteOwnerToMemberWhenAnotherOwnerExists(string $organizationId, string $memberId): bool;

    public function deleteOwnerWhenAnotherOwnerExists(string $organizationId, string $memberId): bool;

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void;

    public function findById(string $id): ?OrganizationMember;

    public function findByOrganizationAndUser(string $organizationId, string $userId): ?OrganizationMember;

    /**
     * @return list<OrganizationMember>
     */
    public function findByOrganization(string $organizationId): array;

    /**
     * @return array{items: list<OrganizationMember>, total: int, currentPage: int, perPage: int}
     */
    public function findPaginatedByOrganization(string $organizationId, PaginationCriteria $paginationCriteria): array;

    public function countByOrganization(string $organizationId): int;

    /**
     * @return list<OrganizationMember>
     */
    public function findByUserId(string $userId): array;
}
