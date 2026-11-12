<?php

declare(strict_types=1);

namespace Packio\Release\Domain\Repository;

use Packio\Release\Domain\Entity\Release;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

interface ReleaseRepositoryInterface
{
    public function save(Release $release): Release;

    public function upsertByNaturalKey(Release $release): Release;

    public function incrementDownloadCount(string $organizationId, string $packageName, string $version): bool;

    /**
     * @param array<string, mixed> $storageData
     */
    public function updateDistStorageMetadataByNaturalKey(
        string $organizationId,
        string $packageName,
        string $version,
        string $expectedReference,
        array $storageData,
    ): ?Release;

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void;

    public function findByPackageAndVersion(string $packageName, string $version, string $organizationId): ?Release;

    /**
     * @return list<Release>
     */
    public function findByPackageName(string $packageName, string $organizationId): array;

    /**
     * Active releases only, newest first by created_at.
     *
     * @return list<array{package_name: string, version: string}>
     */
    public function findActiveVersionsByOrganization(string $organizationId): array;

    /**
     * Distinct organization and package name pairs that have releases.
     *
     * @return list<array{organization_id: string, package_name: string}>
     */
    public function findPackageKeys(): array;
}
