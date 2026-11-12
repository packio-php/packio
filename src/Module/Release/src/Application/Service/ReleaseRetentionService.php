<?php

declare(strict_types=1);

namespace Packio\Release\Application\Service;

use Packio\Release\Api\Dto\ReleaseRetentionResultDto;
use Packio\Release\Domain\Entity\Release;
use Packio\Release\Domain\Repository\ReleaseRepositoryInterface;

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function str_ends_with;
use function str_starts_with;
use function trim;
use function usort;

final readonly class ReleaseRetentionService
{
    public function __construct(
        private ReleaseRepositoryInterface $releaseRepository,
        private ReleaseVersionComparator $releaseVersionComparator,
    ) {}

    public function applyForSourcePackage(
        string $organizationId,
        string $sourceId,
        string $packageName,
        int $keepLastReleases,
    ): ReleaseRetentionResultDto {
        $sourceReleases = $this->sourceReleases($organizationId, $sourceId, $packageName);
        $stableReleases = [];
        $devReleases    = [];

        foreach ($sourceReleases as $release) {
            if ($this->isDevRelease($release)) {
                $devReleases[] = $release;

                continue;
            }

            $stableReleases[] = $release;
        }

        usort(
            $stableReleases,
            fn (Release $left, Release $right): int => $this->releaseVersionComparator->compare(
                $left->version(),
                $right->version(),
            ),
        );

        if ($keepLastReleases <= 0) {
            return new ReleaseRetentionResultDto(
                limit: 0,
                keptCount: count($stableReleases),
                deactivatedCount: 0,
                ignoredDevCount: count($devReleases),
                keptVersions: array_map(
                    static fn (Release $release): string => $release->version(),
                    $stableReleases,
                ),
                ignoredDevVersions: array_map(
                    static fn (Release $release): string => $release->version(),
                    $devReleases,
                ),
            );
        }

        $keptReleases        = array_slice($stableReleases, 0, $keepLastReleases);
        $deactivatedReleases = array_slice($stableReleases, $keepLastReleases);

        foreach ($deactivatedReleases as $release) {
            $this->deactivate($release);
        }

        return new ReleaseRetentionResultDto(
            limit: $keepLastReleases,
            keptCount: count($keptReleases),
            deactivatedCount: count($deactivatedReleases),
            ignoredDevCount: count($devReleases),
            keptVersions: array_map(
                static fn (Release $release): string => $release->version(),
                $keptReleases,
            ),
            deactivatedVersions: array_map(
                static fn (Release $release): string => $release->version(),
                $deactivatedReleases,
            ),
            ignoredDevVersions: array_map(
                static fn (Release $release): string => $release->version(),
                $devReleases,
            ),
        );
    }

    /**
     * @return list<Release>
     */
    private function sourceReleases(string $organizationId, string $sourceId, string $packageName): array
    {
        $resolvedSourceId = trim($sourceId);
        if ('' === $resolvedSourceId) {
            return [];
        }

        return array_values(array_filter(
            $this->releaseRepository->findByPackageName($packageName, trim($organizationId)),
            static fn (Release $release): bool => ($release->sourceData()['source_id'] ?? null) === $resolvedSourceId,
        ));
    }

    private function isDevRelease(Release $release): bool
    {
        return str_starts_with($release->version(), 'dev-')
            || str_ends_with($release->version(), '-dev');
    }

    private function deactivate(Release $release): void
    {
        if (! $release->isActive()) {
            return;
        }

        $release->deactivate();
        $this->releaseRepository->save($release);
    }
}
