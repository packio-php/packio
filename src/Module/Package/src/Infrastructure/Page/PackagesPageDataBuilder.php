<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Page;

use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\PaginatedResultDto;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Source\Api\SourceApiFacadeInterface;

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function ceil;
use function count;
use function implode;
use function in_array;
use function is_array;
use function max;
use function min;
use function str_contains;
use function strcmp;
use function strtolower;
use function usort;

final readonly class PackagesPageDataBuilder
{
    public function __construct(
        private PackageApiFacadeInterface $packageApiFacade,
        private ReleaseApiFacadeInterface $releaseApiFacade,
        private SourceApiFacadeInterface $sourceApiFacade,
    ) {}

    /**
     * @return array{
     *     organization_summary: array{id: string, slug: string, name: string, anonymous_access: bool},
     *     package_stats: array{total: int, listed: int, public: int, private: int},
     *     sections: array<array<string, mixed>>,
     *     package_filter_options: array{source_sync_statuses: list<array{value: string, label: string}>},
     *     filters: array<string, mixed>,
     *     pagination: array<string, mixed>
     * }
     */
    public function build(
        OrganizationDto $organizationDto,
        ?PaginationCriteria $paginationCriteria = null,
        ?PackageIndexFilterCriteria $packageIndexFilterCriteria = null,
    ): array {
        $paginationCriteria ??= new PaginationCriteria();
        $packageIndexFilterCriteria ??= new PackageIndexFilterCriteria();

        $latestVersions = $this->releaseApiFacade->latestActiveVersions($organizationDto->id);

        $sourcesById = [];
        foreach ($this->sourceApiFacade->sourcesForOrganization($organizationDto->id) as $sourceDto) {
            $sourcesById[$sourceDto->id] = $sourceDto;
        }

        $sections = array_map(
            static function(PackageDto $packageDto) use ($latestVersions, $sourcesById): array {
                $sourceDto = '' !== $packageDto->sourceId ? $sourcesById[$packageDto->sourceId] ?? null : null;

                return [
                    'name'           => $packageDto->name,
                    'description'    => $packageDto->description,
                    'latest_version' => $latestVersions[$packageDto->name] ?? null,
                    'visibility'     => $packageDto->visibility,
                    'active'         => $packageDto->active,
                    'source_url'     => $packageDto->sourceUrl,
                    'download_count' => $packageDto->downloadCount,
                    'source'         => $sourceDto ? [
                        'id'                      => $sourceDto->id,
                        'name'                    => $sourceDto->owner . '/' . $sourceDto->repository,
                        'url'                     => $sourceDto->url,
                        'type'                    => $sourceDto->type,
                        'visibility'              => $sourceDto->visibility,
                        'active'                  => $sourceDto->active,
                        'archived'                => $sourceDto->archived,
                        'status'                  => $sourceDto->status,
                        'last_sync_status'        => $sourceDto->lastSyncStatus,
                        'last_successful_sync_at' => $sourceDto->lastSuccessfulSyncAt,
                        'last_failed_sync_at'     => $sourceDto->lastFailedSyncAt,
                    ] : null,
                ];
            },
            $this->packageApiFacade->packagesForOrganization($organizationDto->id),
        );
        $filteredSections   = $this->filterSections($sections, $packageIndexFilterCriteria);
        $paginatedResultDto = $this->paginate($filteredSections, $paginationCriteria);
        $pagination         = $paginatedResultDto->toArray();
        unset($pagination['items']);

        return [
            'organization_summary'   => [
                'id'               => $organizationDto->id,
                'slug'             => $organizationDto->slug,
                'name'             => $organizationDto->name,
                'anonymous_access' => $organizationDto->anonymousAccess,
            ],
            'package_stats'          => [
                'total'   => count($sections),
                'listed'  => count(array_filter($sections, static fn (array $section): bool => true === $section['active'])),
                'public'  => count(array_filter($sections, static fn (array $section): bool => 'public' === $section['visibility'])),
                'private' => count(array_filter($sections, static fn (array $section): bool => 'private' === $section['visibility'])),
            ],
            'sections'               => $paginatedResultDto->items,
            'package_filter_options' => $this->packageFilterOptions($sections),
            'filters'                => [
                ...$packageIndexFilterCriteria->toQueryArray(),
                'page'     => $paginatedResultDto->currentPage,
                'per_page' => $paginatedResultDto->perPage,
            ],
            'pagination'             => $pagination,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return PaginatedResultDto<array<string, mixed>>
     */
    private function paginate(array $items, PaginationCriteria $paginationCriteria): PaginatedResultDto
    {
        $total       = count($items);
        $perPage     = $paginationCriteria->perPage;
        $lastPage    = max(1, (int) ceil($total / $perPage));
        $currentPage = min($paginationCriteria->page, $lastPage);

        return new PaginatedResultDto(
            array_slice($items, ($currentPage - 1) * $perPage, $perPage),
            $total,
            $currentPage,
            $perPage,
        );
    }

    /**
     * @param list<array<string, mixed>> $sections
     *
     * @return list<array<string, mixed>>
     */
    private function filterSections(array $sections, PackageIndexFilterCriteria $packageIndexFilterCriteria): array
    {
        return array_values(array_filter(
            $sections,
            static fn (array $section): bool => self::matchesFilters($section, $packageIndexFilterCriteria),
        ));
    }

    /**
     * @param array<string, mixed> $section
     */
    private static function matchesFilters(array $section, PackageIndexFilterCriteria $packageIndexFilterCriteria): bool
    {
        if (
            null !== $packageIndexFilterCriteria->search
            && ! str_contains(self::packageSearchHaystack($section), strtolower($packageIndexFilterCriteria->search))
        ) {
            return false;
        }

        if ([] !== $packageIndexFilterCriteria->visibilities && ! in_array((string) ($section['visibility'] ?? ''), $packageIndexFilterCriteria->visibilities, true)) {
            return false;
        }

        if ('listed' === $packageIndexFilterCriteria->listed && true !== ($section['active'] ?? false)) {
            return false;
        }

        if ('hidden' === $packageIndexFilterCriteria->listed && true === ($section['active'] ?? false)) {
            return false;
        }

        $source = $section['source'] ?? null;
        if ('managed' === $packageIndexFilterCriteria->source && ! is_array($source)) {
            return false;
        }

        if ('manual' === $packageIndexFilterCriteria->source && is_array($source)) {
            return false;
        }

        if ([] !== $packageIndexFilterCriteria->sourceSyncStatuses) {
            if (! is_array($source)) {
                return false;
            }

            if (! in_array((string) ($source['last_sync_status'] ?? ''), $packageIndexFilterCriteria->sourceSyncStatuses, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $section
     */
    private static function packageSearchHaystack(array $section): string
    {
        $source = $section['source'] ?? null;

        return strtolower(implode(' ', [
            (string) ($section['name'] ?? ''),
            (string) ($section['description'] ?? ''),
            (string) ($section['source_url'] ?? ''),
            is_array($source) ? (string) ($source['name'] ?? '') : '',
            is_array($source) ? (string) ($source['url'] ?? '') : '',
        ]));
    }

    /**
     * @param list<array<string, mixed>> $sections
     *
     * @return array{source_sync_statuses: list<array{value: string, label: string}>}
     */
    private function packageFilterOptions(array $sections): array
    {
        $syncStatuses = [];

        foreach ($sections as $section) {
            $source = $section['source'] ?? null;
            if (! is_array($source)) {
                continue;
            }

            $syncStatus = (string) ($source['last_sync_status'] ?? '');
            if ('' === $syncStatus) {
                continue;
            }

            $syncStatuses[$syncStatus] = [
                'value' => $syncStatus,
                'label' => $syncStatus,
            ];
        }

        $syncStatusOptions = array_values($syncStatuses);
        usort(
            $syncStatusOptions,
            static fn (array $left, array $right): int => strcmp($left['value'], $right['value']),
        );

        return [
            'source_sync_statuses' => $syncStatusOptions,
        ];
    }
}
