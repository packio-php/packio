<?php

declare(strict_types=1);

namespace Packio\Organization\Domain\Repository;

use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Domain\Value\OrganizationDeletionInitiation;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

interface OrganizationRepositoryInterface
{
    public function countAll(): int;

    public function save(Organization $organization): Organization;

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void;

    public function findById(string $id): ?Organization;

    /**
     * Atomically transitions an organization to `deleting` only when it is
     * not already deleting. The row is locked for update, so concurrent or
     * repeated initiations get exactly one `Initiated` outcome.
     */
    public function initiateDeletion(string $id): OrganizationDeletionInitiation;

    /**
     * Compensating revert of a successful initiation: restores the exact
     * pre-initiation state. Only applies while the organization is still
     * `deleting`, so it can never overwrite a later lifecycle decision.
     */
    public function revertInitiation(string $id, bool $previousActive, string $deletionRunId): void;

    /**
     * Returns the durable deletion generation, allocating one under the row
     * lock when a legacy `deleting` organization predates the generation
     * column backfill.
     */
    public function ensureDeletionRun(string $id): ?string;

    /** True only while this exact deletion generation is still current. */
    public function isDeletionRunCurrent(string $id, string $deletionRunId): bool;

    public function findBySlug(string $slug): ?Organization;

    /**
     * @param list<string> $ids
     *
     * @return list<Organization>
     */
    public function findByIds(array $ids): array;

    /**
     * @return list<Organization>
     */
    public function findAll(): array;

    public function existsWithNameKey(string $nameKey, ?string $exceptOrganizationId = null): bool;
}
