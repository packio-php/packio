<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Page;

use Packio\Admin\Infrastructure\Request\AdminSourceFilterQuery;
use Packio\Admin\Infrastructure\SourceDeletion\SourceDeletionRecoveryService;
use Packio\Admin\Infrastructure\SourceDeletion\SourceDeletionRecoverySummary;
use Packio\Application\Service\InitialListViewModeResolver;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
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
use function str_contains;
use function strcmp;
use function strtolower;
use function substr;
use function usort;

final readonly class AdminSourcesPageDataBuilder
{
    private const string VIEW_MODE_STORAGE_KEY = 'packio-admin-sources-view-mode';

    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private SourceDeletionRecoveryService $sourceDeletionRecoveryService,
        private InitialListViewModeResolver $initialListViewModeResolver,
        private AdminOrganizationPresenter $adminOrganizationPresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(ServerRequestInterface $serverRequest): array
    {
        $adminSourceFilterQuery = AdminSourceFilterQuery::fromQueryParams($serverRequest->getQueryParams());

        $organizations     = $this->organizationIndex();
        // shortcut: filters and pages in PHP over all rows, move to SQL when admin lists reach tens of thousands.
        $sourceDtos        = $this->sourceApiFacade->allSources();
        $recoverySummaries = $this->sourceDeletionRecoveryService->summariesForSources($sourceDtos);
        $sources           = array_map(
            fn (SourceDto $sourceDto): array => $this->sourceToArray(
                $sourceDto,
                $organizations[$sourceDto->organizationId] ?? null,
                $recoverySummaries[$sourceDto->id] ?? null,
            ),
            $sourceDtos,
        );

        $filteredSources    = $this->sortSources($this->filterSources($sources, $adminSourceFilterQuery), $adminSourceFilterQuery);
        $paginatedResultDto = InMemoryPagination::paginate($filteredSources, $adminSourceFilterQuery->paginationCriteria);
        $pagination         = $paginatedResultDto->toArray();
        unset($pagination['items']);

        return [
            'sources'                => array_values($paginatedResultDto->items),
            'pagination'             => $pagination,
            'filters'                => [
                ...$adminSourceFilterQuery->filters,
                'page'     => $paginatedResultDto->currentPage,
                'per_page' => $paginatedResultDto->perPage,
            ],
            'source_stats'           => $this->stats($sources),
            'filter_options'         => $this->filterOptions($sources),
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
     * @param null|array{id: string, slug: string, name: string, status: string} $organization
     *
     * @return array<string, mixed>
     */
    private function sourceToArray(
        SourceDto $sourceDto,
        ?array $organization,
        ?SourceDeletionRecoverySummary $sourceDeletionRecoverySummary
    ): array {
        return [
            'id'                      => $sourceDto->id,
            'organization'            => $organization,
            'provider'                => $sourceDto->provider,
            'provider_label'          => $this->sourceApiFacade->sourceProviderLabel($sourceDto->provider),
            'name'                    => '' !== $sourceDto->displayName
                ? $sourceDto->displayName
                : $sourceDto->owner . '/' . $sourceDto->repository,
            'url'                     => $sourceDto->url,
            'canonical_url'           => $sourceDto->canonicalUrl,
            'owner'                   => $sourceDto->owner,
            'repository'              => $sourceDto->repository,
            'default_ref'             => $sourceDto->defaultRef,
            'visibility'              => $sourceDto->visibility,
            'active'                  => $sourceDto->active,
            'archived'                => $sourceDto->archived,
            'archived_at'             => $sourceDto->archivedAt,
            'status'                  => $sourceDto->status,
            'logical_status'          => $this->logicalStatus($sourceDto),
            'last_sync_status'        => $sourceDto->lastSyncStatus,
            'last_successful_sync_at' => $sourceDto->lastSuccessfulSyncAt,
            'last_failed_sync_at'     => $sourceDto->lastFailedSyncAt,
            'last_sync_error'         => $sourceDto->lastSyncError,
            'created_at'              => $sourceDto->createdAt,
            'updated_at'              => $sourceDto->updatedAt,
            'keep_last_releases'      => $sourceDto->keepLastReleases,
            'recovery'                => $sourceDeletionRecoverySummary?->toArray(),
        ];
    }

    private function logicalStatus(SourceDto $sourceDto): string
    {
        if ('deleting' === $sourceDto->status) {
            return 'deleting';
        }

        if ($sourceDto->archived) {
            return 'archived';
        }

        return $sourceDto->active ? 'active' : 'inactive';
    }

    /**
     * @param list<array<string, mixed>> $sources
     *
     * @return list<array<string, mixed>>
     */
    private function filterSources(array $sources, AdminSourceFilterQuery $adminSourceFilterQuery): array
    {
        return array_values(array_filter(
            $sources,
            static fn (array $source): bool => self::matchesSource($source, $adminSourceFilterQuery),
        ));
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function matchesSource(array $source, AdminSourceFilterQuery $adminSourceFilterQuery): bool
    {
        if ('' !== $adminSourceFilterQuery->organizationId && ($source['organization']['id'] ?? '') !== $adminSourceFilterQuery->organizationId) {
            return false;
        }

        if ('' !== $adminSourceFilterQuery->search && ! str_contains(self::sourceHaystack($source), strtolower($adminSourceFilterQuery->search))) {
            return false;
        }

        if ([] !== $adminSourceFilterQuery->provider && ! in_array((string) ($source['provider'] ?? ''), $adminSourceFilterQuery->provider, true)) {
            return false;
        }

        if ([] !== $adminSourceFilterQuery->visibility && ! in_array((string) ($source['visibility'] ?? ''), $adminSourceFilterQuery->visibility, true)) {
            return false;
        }

        if ([] !== $adminSourceFilterQuery->status && ! in_array((string) ($source['logical_status'] ?? ''), $adminSourceFilterQuery->status, true)) {
            return false;
        }

        if ([] !== $adminSourceFilterQuery->syncStatus && ! in_array((string) ($source['last_sync_status'] ?? ''), $adminSourceFilterQuery->syncStatus, true)) {
            return false;
        }

        $recovery = $source['recovery'] ?? null;
        if (
            [] !== $adminSourceFilterQuery->deleteHealth
            && ! in_array(is_array($recovery) ? (string) ($recovery['health'] ?? '') : '', $adminSourceFilterQuery->deleteHealth, true)
        ) {
            return false;
        }

        if ('' !== $adminSourceFilterQuery->sourceId && ! str_contains(strtolower((string) ($source['id'] ?? '')), strtolower($adminSourceFilterQuery->sourceId))) {
            return false;
        }

        if ('' !== $adminSourceFilterQuery->url && ! str_contains(strtolower((string) ($source['url'] ?? '')), strtolower($adminSourceFilterQuery->url))) {
            return false;
        }

        if ('' !== $adminSourceFilterQuery->owner && ! str_contains(strtolower((string) ($source['owner'] ?? '')), strtolower($adminSourceFilterQuery->owner))) {
            return false;
        }

        if ('' !== $adminSourceFilterQuery->repository && ! str_contains(strtolower((string) ($source['repository'] ?? '')), strtolower($adminSourceFilterQuery->repository))) {
            return false;
        }

        if ('' !== $adminSourceFilterQuery->defaultRef && ! str_contains(strtolower((string) ($source['default_ref'] ?? '')), strtolower($adminSourceFilterQuery->defaultRef))) {
            return false;
        }

        if (! self::dateInRange((string) ($source['created_at'] ?? ''), $adminSourceFilterQuery->createdFrom, $adminSourceFilterQuery->createdTo)) {
            return false;
        }

        return self::dateInRange((string) ($source['archived_at'] ?? ''), $adminSourceFilterQuery->archivedFrom, $adminSourceFilterQuery->archivedTo);
    }

    /**
     * @param list<array<string, mixed>> $sources
     *
     * @return list<array<string, mixed>>
     */
    private function sortSources(array $sources, AdminSourceFilterQuery $adminSourceFilterQuery): array
    {
        usort(
            $sources,
            static fn (array $left, array $right): int => self::compareSource(
                $left,
                $right,
                $adminSourceFilterQuery->sort,
                $adminSourceFilterQuery->direction,
            ),
        );

        return $sources;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private static function compareSource(array $left, array $right, string $sort, string $direction): int
    {
        $leftValue  = self::sourceSortValue($left, $sort);
        $rightValue = self::sourceSortValue($right, $sort);

        $comparison = strcmp(strtolower($leftValue), strtolower($rightValue));

        if (0 === $comparison) {
            $comparison = strcmp(
                strtolower((string) ($left['id'] ?? '')),
                strtolower((string) ($right['id'] ?? '')),
            );
        }

        return 'asc' === $direction ? $comparison : -$comparison;
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function sourceSortValue(array $source, string $sort): string
    {
        $organization = $source['organization'] ?? null;

        return match ($sort) {
            'organization'     => is_array($organization) ? (string) ($organization['name'] ?? '') : '',
            'provider'         => (string) ($source['provider'] ?? ''),
            'visibility'       => (string) ($source['visibility'] ?? ''),
            'logical_status'   => (string) ($source['logical_status'] ?? ''),
            'last_sync_status' => (string) ($source['last_sync_status'] ?? ''),
            'created_at'       => (string) ($source['created_at'] ?? ''),
            default            => (string) ($source['name'] ?? ''),
        };
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function sourceHaystack(array $source): string
    {
        $organization = $source['organization'] ?? null;

        return strtolower(implode(' ', [
            (string) ($source['id'] ?? ''),
            (string) ($source['name'] ?? ''),
            ($source['owner'] ?? '') . '/' . ($source['repository'] ?? ''),
            (string) ($source['url'] ?? ''),
            (string) ($source['canonical_url'] ?? ''),
            (string) ($source['provider'] ?? ''),
            (string) ($source['provider_label'] ?? ''),
            (string) ($source['default_ref'] ?? ''),
            is_array($organization) ? (string) ($organization['name'] ?? '') : '',
            is_array($organization) ? (string) ($organization['slug'] ?? '') : '',
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

        $date = substr($value, 0, 10);

        return ('' === $from || $date >= $from) && ('' === $to || $date <= $to);
    }

    /**
     * @param list<array<string, mixed>> $sources
     *
     * @return array{total: int, active: int, inactive: int, archived: int, deleting: int, deleting_missing_jobs: int, deleting_failed: int, deleting_in_progress: int, public: int, private: int}
     */
    private function stats(array $sources): array
    {
        return [
            'total'                 => count($sources),
            'active'                => count(array_filter($sources, static fn (array $source): bool => 'active' === ($source['logical_status'] ?? ''))),
            'inactive'              => count(array_filter($sources, static fn (array $source): bool => 'inactive' === ($source['logical_status'] ?? ''))),
            'archived'              => count(array_filter($sources, static fn (array $source): bool => 'archived' === ($source['logical_status'] ?? ''))),
            'deleting'              => count(array_filter($sources, static fn (array $source): bool => 'deleting' === ($source['logical_status'] ?? ''))),
            'deleting_missing_jobs' => count(array_filter($sources, static fn (array $source): bool => SourceDeletionRecoverySummary::HEALTH_STALE_WITHOUT_JOB === self::recoveryHealth($source))),
            'deleting_failed'       => count(array_filter($sources, static fn (array $source): bool => SourceDeletionRecoverySummary::HEALTH_FAILED === self::recoveryHealth($source))),
            'deleting_in_progress'  => count(array_filter($sources, static fn (array $source): bool => SourceDeletionRecoverySummary::HEALTH_IN_PROGRESS === self::recoveryHealth($source))),
            'public'                => count(array_filter($sources, static fn (array $source): bool => 'public' === ($source['visibility'] ?? ''))),
            'private'               => count(array_filter($sources, static fn (array $source): bool => 'private' === ($source['visibility'] ?? ''))),
        ];
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function recoveryHealth(array $source): string
    {
        $recovery = $source['recovery'] ?? null;

        return is_array($recovery) ? (string) ($recovery['health'] ?? '') : '';
    }

    /**
     * @param list<array<string, mixed>> $sources
     *
     * @return array{providers: list<array{value: string, label: string}>, sync_statuses: list<array{value: string, label: string}>}
     */
    private function filterOptions(array $sources): array
    {
        $providers    = [];
        $syncStatuses = [];

        foreach ($sources as $source) {
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
            'providers'     => $providerOptions,
            'sync_statuses' => $syncStatusOptions,
        ];
    }
}
