<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Repository;

use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Value\SourceDeletionInitiation;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

interface SourceRepositoryInterface
{
    public function countAll(): int;

    public function save(Source $source): Source;

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void;

    public function findById(string $id): ?Source;

    public function findByIdInOrganization(string $id, string $organizationId): ?Source;

    /**
     * Atomically transitions a source to `deleting` only when it is not
     * already deleting. The row is locked for update, so concurrent or
     * repeated initiations get exactly one `Initiated` outcome.
     */
    public function initiateDeletion(string $id, string $organizationId): SourceDeletionInitiation;

    /**
     * Compensating revert of a successful initiation: restores the exact
     * pre-initiation state. Only applies while the source is still
     * `deleting`, so it can never overwrite a later lifecycle decision.
     */
    public function revertInitiation(
        string $id,
        string $organizationId,
        bool $previousActive,
        string $deletionRunId,
    ): void;

    /** Ensures organization-driven deletion uses its exact lifecycle run. */
    public function ensureDeletionRun(string $id, string $organizationId, string $deletionRunId): bool;

    /** Returns the current deletion generation without consulting the ORM heap. */
    public function deletionRunId(string $id, string $organizationId): ?string;

    /**
     * Returns the current deleting generation, repairing a legacy missing run
     * or atomically replacing it when forced recovery is requested.
     */
    public function recoverDeletionRun(string $id, string $organizationId, bool $force): ?string;

    /** True only while this exact deletion generation is still current. */
    public function isDeletionRunCurrent(string $id, string $organizationId, string $deletionRunId): bool;

    public function countForOrganization(string $organizationId): int;

    public function findByUrl(string $url, ?string $organizationId = null): ?Source;

    /**
     * @return list<Source>
     */
    public function findByOrganization(string $organizationId): array;

    /**
     * @return list<Source>
     */
    public function findAll(): array;

    /**
     * Sources ordered by id, starting after the given id (keyset batches).
     *
     * @return list<Source>
     */
    public function findBatchAfter(?string $afterId, int $limit): array;

    /**
     * @return list<Source>
     */
    public function findByProviderAuthorizationId(string $providerAuthorizationId, string $organizationId): array;

    /**
     * @param list<string> $providerAuthorizationIds
     *
     * @return list<Source>
     */
    public function findByProviderAuthorizationIds(array $providerAuthorizationIds, string $organizationId): array;

    /**
     * @return list<Source>
     */
    public function findByProviderInstance(string $providerInstanceId, string $organizationId): array;

    /**
     * @return list<Source>
     */
    public function findLockedByProviderInstance(string $providerInstanceId, string $organizationId): array;

    /**
     * @param list<string> $providerAuthorizationIds
     *
     * @return list<Source>
     */
    public function findLockedByProviderAuthorizationIds(array $providerAuthorizationIds, string $organizationId): array;
}
