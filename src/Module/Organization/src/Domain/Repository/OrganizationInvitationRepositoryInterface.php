<?php

declare(strict_types=1);

namespace Packio\Organization\Domain\Repository;

use Packio\Organization\Domain\Entity\OrganizationInvitation;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

interface OrganizationInvitationRepositoryInterface
{
    public function save(OrganizationInvitation $organizationInvitation): OrganizationInvitation;

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void;

    public function findById(string $id): ?OrganizationInvitation;

    public function findByToken(string $token): ?OrganizationInvitation;

    /**
     * @return list<OrganizationInvitation>
     */
    public function findByOrganization(string $organizationId): array;

    /**
     * @return list<OrganizationInvitation>
     */
    public function findBatchAfter(?string $afterId, int $limit): array;

    public function countByOrganization(string $organizationId): int;
}
