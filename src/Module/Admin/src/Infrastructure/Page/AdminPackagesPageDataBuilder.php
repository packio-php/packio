<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Page;

use Packio\Admin\Infrastructure\Request\AdminPackageFilterQuery;
use Packio\Application\Service\InitialListViewModeResolver;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function str_contains;
use function strcmp;
use function strtolower;
use function substr;
use function usort;

final readonly class AdminPackagesPageDataBuilder
{
    private const string VIEW_MODE_STORAGE_KEY = 'packio-admin-packages-view-mode';

    public function __construct(
        private PackageApiFacadeInterface $packageApiFacade,
        private SourceApiFacadeInterface $sourceApiFacade,
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InitialListViewModeResolver $initialListViewModeResolver,
        private AdminOrganizationPresenter $adminOrganizationPresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(ServerRequestInterface $serverRequest): array
    {
        $adminPackageFilterQuery = AdminPackageFilterQuery::fromQueryParams($serverRequest->getQueryParams());

        $organizations = $this->organizationIndex();
        $sources       = $this->sourceIndex();
        $packages      = array_map(
            fn (PackageDto $packageDto): array => $this->packageToArray(
                $packageDto,
                $organizations[$packageDto->organizationId] ?? null,
                '' !== $packageDto->sourceId ? $sources[$packageDto->sourceId] ?? null : null,
            ),
            // shortcut: filters and pages in PHP over all rows, move to SQL when admin lists reach tens of thousands.
            $this->packageApiFacade->allPackages(),
        );

        $filteredPackages   = $this->sortPackages($this->filterPackages($packages, $adminPackageFilterQuery), $adminPackageFilterQuery);
        $paginatedResultDto = InMemoryPagination::paginate($filteredPackages, $adminPackageFilterQuery->paginationCriteria);
        $pagination         = $paginatedResultDto->toArray();
        unset($pagination['items']);

        return [
            'packages'               => array_values($paginatedResultDto->items),
            'pagination'             => $pagination,
            'filters'                => [
                ...$adminPackageFilterQuery->filters,
                'page'     => $paginatedResultDto->currentPage,
                'per_page' => $paginatedResultDto->perPage,
            ],
            'package_stats'          => $this->stats($packages),
            'filter_options'         => $this->filterOptions($packages),
            'organizations'          => array_values($organizations),
            'initial_list_view_mode' => $this->initialListViewModeResolver->resolve(
                $serverRequest,
                self::VIEW_MODE_STORAGE_KEY,
                'table',
            ),
        ];
    }

    /**
     * @return array<string, array{id: string, slug: string, name: string, status: string}>
     */
    private function organizationIndex(): array
    {
        $organizations = [];
        foreach ($this->organizationApiFacade->allOrganizations() as $organizationDto) {
            $organizations[$organizationDto->id] = $this->adminOrganizationPresenter->present($organizationDto);
        }

        return $organizations;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sourceIndex(): array
    {
        $sources = [];
        foreach ($this->sourceApiFacade->allSources() as $sourceDto) {
            $sources[$sourceDto->id] = $this->sourceSummary($sourceDto);
        }

        return $sources;
    }

    /**
     * @param null|array{id: string, slug: string, name: string, status: string} $organization
     * @param null|array<string, mixed>                                          $source
     *
     * @return array<string, mixed>
     */
    private function packageToArray(PackageDto $packageDto, ?array $organization, ?array $source): array
    {
        return [
            'name'           => $packageDto->name,
            'type'           => $packageDto->type,
            'description'    => $packageDto->description,
            'visibility'     => $packageDto->visibility,
            'active'         => $packageDto->active,
            'source_id'      => $packageDto->sourceId,
            'source_type'    => $packageDto->sourceType,
            'source_url'     => $packageDto->sourceUrl,
            'created_at'     => $packageDto->createdAt,
            'updated_at'     => $packageDto->updatedAt,
            'download_count' => $packageDto->downloadCount,
            'organization'   => $organization,
            'source'         => $source,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceSummary(SourceDto $sourceDto): array
    {
        return [
            'id'               => $sourceDto->id,
            'name'             => '' !== $sourceDto->displayName
                ? $sourceDto->displayName
                : $sourceDto->owner . '/' . $sourceDto->repository,
            'provider'         => $sourceDto->provider,
            'provider_label'   => $this->sourceApiFacade->sourceProviderLabel($sourceDto->provider),
            'url'              => $sourceDto->url,
            'active'           => $sourceDto->active,
            'archived'         => $sourceDto->archived,
            'status'           => $sourceDto->status,
            'last_sync_status' => $sourceDto->lastSyncStatus,
        ];
    }

    /**
     * @param list<array<string, mixed>> $packages
     *
     * @return list<array<string, mixed>>
     */
    private function filterPackages(array $packages, AdminPackageFilterQuery $adminPackageFilterQuery): array
    {
        return array_values(array_filter(
            $packages,
            static fn (array $package): bool => self::matchesPackage($package, $adminPackageFilterQuery),
        ));
    }

    /**
     * @param array<string, mixed> $package
     */
    private static function matchesPackage(array $package, AdminPackageFilterQuery $adminPackageFilterQuery): bool
    {
        if ('' !== $adminPackageFilterQuery->organizationId && ($package['organization']['id'] ?? '') !== $adminPackageFilterQuery->organizationId) {
            return false;
        }

        if ('' !== $adminPackageFilterQuery->search && ! str_contains(self::packageHaystack($package), strtolower($adminPackageFilterQuery->search))) {
            return false;
        }

        if ([] !== $adminPackageFilterQuery->visibility && ! in_array((string) ($package['visibility'] ?? ''), $adminPackageFilterQuery->visibility, true)) {
            return false;
        }

        if ('listed' === $adminPackageFilterQuery->listed && true !== ($package['active'] ?? false)) {
            return false;
        }

        if ('hidden' === $adminPackageFilterQuery->listed && true === ($package['active'] ?? false)) {
            return false;
        }

        $source = $package['source'] ?? null;
        if ('managed' === $adminPackageFilterQuery->source && ! is_array($source)) {
            return false;
        }

        if ('manual' === $adminPackageFilterQuery->source && is_array($source)) {
            return false;
        }

        if ([] !== $adminPackageFilterQuery->sourceProvider && (! is_array($source) || ! in_array((string) ($source['provider'] ?? ''), $adminPackageFilterQuery->sourceProvider, true))) {
            return false;
        }

        if ([] !== $adminPackageFilterQuery->sourceSyncStatus && (! is_array($source) || ! in_array((string) ($source['last_sync_status'] ?? ''), $adminPackageFilterQuery->sourceSyncStatus, true))) {
            return false;
        }

        if ('' !== $adminPackageFilterQuery->packageName && ! str_contains(strtolower((string) ($package['name'] ?? '')), strtolower($adminPackageFilterQuery->packageName))) {
            return false;
        }

        if ('' !== $adminPackageFilterQuery->sourceId && ! str_contains(strtolower((string) ($package['source_id'] ?? '')), strtolower($adminPackageFilterQuery->sourceId))) {
            return false;
        }

        if ('' !== $adminPackageFilterQuery->sourceUrl && ! str_contains(strtolower((string) ($package['source_url'] ?? '')), strtolower($adminPackageFilterQuery->sourceUrl))) {
            return false;
        }

        if (! self::dateInRange((string) ($package['created_at'] ?? ''), $adminPackageFilterQuery->createdFrom, $adminPackageFilterQuery->createdTo)) {
            return false;
        }

        if (! self::dateInRange((string) ($package['updated_at'] ?? ''), $adminPackageFilterQuery->updatedFrom, $adminPackageFilterQuery->updatedTo)) {
            return false;
        }

        $downloads = (int) ($package['download_count'] ?? 0);
        if (null !== $adminPackageFilterQuery->minDownloads && $downloads < $adminPackageFilterQuery->minDownloads) {
            return false;
        }

        return null === $adminPackageFilterQuery->maxDownloads || $downloads <= $adminPackageFilterQuery->maxDownloads;
    }

    /**
     * @param list<array<string, mixed>> $packages
     *
     * @return list<array<string, mixed>>
     */
    private function sortPackages(array $packages, AdminPackageFilterQuery $adminPackageFilterQuery): array
    {
        usort(
            $packages,
            static fn (array $left, array $right): int => self::comparePackage(
                $left,
                $right,
                $adminPackageFilterQuery->sort,
                $adminPackageFilterQuery->direction,
            ),
        );

        return $packages;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private static function comparePackage(array $left, array $right, string $sort, string $direction): int
    {
        $leftValue  = self::packageSortValue($left, $sort);
        $rightValue = self::packageSortValue($right, $sort);

        $comparison = is_int($leftValue) && is_int($rightValue)
            ? $leftValue <=> $rightValue
            : strcmp(strtolower((string) $leftValue), strtolower((string) $rightValue));

        if (0 === $comparison) {
            $comparison = strcmp(
                strtolower((string) ($left['name'] ?? '')),
                strtolower((string) ($right['name'] ?? '')),
            );
        }

        return 'asc' === $direction ? $comparison : -$comparison;
    }

    /**
     * @param array<string, mixed> $package
     */
    private static function packageSortValue(array $package, string $sort): int|string
    {
        $organization = $package['organization'] ?? null;
        $source       = $package['source'] ?? null;

        return match ($sort) {
            'organization'   => is_array($organization) ? (string) ($organization['name'] ?? '') : '',
            'visibility'     => (string) ($package['visibility'] ?? ''),
            'active'         => true === ($package['active'] ?? false) ? 1 : 0,
            'download_count' => (int) ($package['download_count'] ?? 0),
            'source'         => is_array($source) ? (string) ($source['name'] ?? '') : 'manual',
            'created_at'     => (string) ($package['created_at'] ?? ''),
            'updated_at'     => (string) ($package['updated_at'] ?? ''),
            default          => (string) ($package['name'] ?? ''),
        };
    }

    /**
     * @param array<string, mixed> $package
     */
    private static function packageHaystack(array $package): string
    {
        $organization = $package['organization'] ?? null;
        $source       = $package['source'] ?? null;

        return strtolower(implode(' ', [
            (string) ($package['name'] ?? ''),
            (string) ($package['description'] ?? ''),
            (string) ($package['source_url'] ?? ''),
            is_array($organization) ? (string) ($organization['name'] ?? '') : '',
            is_array($organization) ? (string) ($organization['slug'] ?? '') : '',
            is_array($source) ? (string) ($source['name'] ?? '') : '',
            is_array($source) ? (string) ($source['url'] ?? '') : '',
            is_array($source) ? (string) ($source['provider'] ?? '') : '',
            is_array($source) ? (string) ($source['provider_label'] ?? '') : '',
        ]));
    }

    private static function dateInRange(string $value, string $from, string $to): bool
    {
        if ('' === $from && '' === $to) {
            return true;
        }

        if ('' === $value) {
            return false;
        }

        $date = self::datePart($value);

        return ('' === $from || $date >= $from) && ('' === $to || $date <= $to);
    }

    private static function datePart(string $value): string
    {
        return substr($value, 0, 10);
    }

    /**
     * @param list<array<string, mixed>> $packages
     *
     * @return array{total: int, listed: int, hidden: int, public: int, private: int, managed: int, manual: int}
     */
    private function stats(array $packages): array
    {
        return [
            'total'   => count($packages),
            'listed'  => count(array_filter($packages, static fn (array $package): bool => true === ($package['active'] ?? false))),
            'hidden'  => count(array_filter($packages, static fn (array $package): bool => true !== ($package['active'] ?? false))),
            'public'  => count(array_filter($packages, static fn (array $package): bool => 'public' === ($package['visibility'] ?? ''))),
            'private' => count(array_filter($packages, static fn (array $package): bool => 'private' === ($package['visibility'] ?? ''))),
            'managed' => count(array_filter($packages, static fn (array $package): bool => is_array($package['source'] ?? null))),
            'manual'  => count(array_filter($packages, static fn (array $package): bool => ! is_array($package['source'] ?? null))),
        ];
    }

    /**
     * @param list<array<string, mixed>> $packages
     *
     * @return array{source_providers: list<array{value: string, label: string}>, source_sync_statuses: list<array{value: string, label: string}>}
     */
    private function filterOptions(array $packages): array
    {
        $providers    = [];
        $syncStatuses = [];

        foreach ($packages as $package) {
            $source = $package['source'] ?? null;
            if (! is_array($source)) {
                continue;
            }

            $provider = (string) ($source['provider'] ?? '');
            if ('' !== $provider) {
                $providers[$provider] = [
                    'value' => $provider,
                    'label' => (string) ($source['provider_label'] ?? $provider),
                ];
            }

            $syncStatus = (string) ($source['last_sync_status'] ?? '');
            if ('' !== $syncStatus) {
                $syncStatuses[$syncStatus] = [
                    'value' => $syncStatus,
                    'label' => $syncStatus,
                ];
            }
        }

        $providerOptions = array_values($providers);
        usort(
            $providerOptions,
            static fn (array $left, array $right): int => strcmp($left['label'], $right['label'])
                ?: strcmp($left['value'], $right['value']),
        );

        $syncStatusOptions = array_values($syncStatuses);
        usort(
            $syncStatusOptions,
            static fn (array $left, array $right): int => strcmp($left['value'], $right['value']),
        );

        return [
            'source_providers'     => $providerOptions,
            'source_sync_statuses' => $syncStatusOptions,
        ];
    }
}
