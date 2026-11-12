<?php

declare(strict_types=1);

namespace Packio\Release\Application\Query;

use Packio\Release\Application\Service\ReleaseVersionComparator;
use Packio\Release\Domain\Entity\Release;
use Packio\Release\Domain\Repository\ReleaseRepositoryInterface;
use RuntimeException;

use function array_filter;
use function array_values;
use function is_array;
use function trim;
use function usort;

final readonly class ReleaseQueryService
{
    public function __construct(
        private ReleaseRepositoryInterface $releaseRepository,
        private ReleaseVersionComparator $releaseVersionComparator
    ) {}

    /**
     * @return list<Release>
     */
    public function releasesForPackage(string $packageName, bool $includeInactive, string $organizationId): array
    {
        $resolvedOrganizationId = $this->requireOrganizationId($organizationId);

        $releases = $this->releaseRepository->findByPackageName(
            $packageName,
            $resolvedOrganizationId,
        );

        if (! $includeInactive) {
            $releases = array_values(
                array_filter(
                    $releases,
                    static fn (Release $release): bool => $release->isActive(),
                ),
            );
        }

        usort(
            $releases,
            fn (
                Release $left,
                Release $right,
            ): int => $this->releaseVersionComparator->compare(
                $left->version(),
                $right->version(),
            ),
        );

        return $releases;
    }

    public function findRelease(string $packageName, string $version, string $organizationId): ?Release
    {
        $resolvedOrganizationId = $this->requireOrganizationId($organizationId);

        return $this->releaseRepository->findByPackageAndVersion(
            $packageName,
            $version,
            $resolvedOrganizationId,
        );
    }

    /**
     * Archive storage keys of all releases (active or not), in repository order, duplicates kept.
     *
     * @return list<string>
     */
    public function distStorageKeysForPackage(string $packageName, string $organizationId): array
    {
        $keys = [];
        foreach ($this->releaseRepository->findByPackageName(
            $packageName,
            $this->requireOrganizationId($organizationId),
        ) as $release) {
            $storage = $release->distData()['storage'] ?? null;
            // Same key parsing as ReleaseDistStorageMetadata::fromArray, but a malformed entry is skipped, not thrown.
            $key = is_array($storage) ? (string) ($storage['key'] ?? '') : '';
            if ('' !== $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, string> package name => highest active version
     */
    public function latestActiveVersions(string $organizationId): array
    {
        $latest = [];
        // Rows come newest first; only a strictly higher version replaces the pick,
        // so ties go to the newer release, like the stable usort in releasesForPackage.
        foreach ($this->releaseRepository->findActiveVersionsByOrganization(
            $this->requireOrganizationId($organizationId),
        ) as $row) {
            $name = $row['package_name'];
            if (! isset($latest[$name]) || $this->releaseVersionComparator->compare($row['version'], $latest[$name]) < 0) {
                $latest[$name] = $row['version'];
            }
        }

        return $latest;
    }

    private function requireOrganizationId(string $organizationId): string
    {
        $resolvedOrganizationId = trim($organizationId);
        if ('' === $resolvedOrganizationId) {
            throw new RuntimeException('Organization is required to query releases.');
        }

        return $resolvedOrganizationId;
    }
}
