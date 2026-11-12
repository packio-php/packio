<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Request;

use Packio\Shared\Domain\Pagination\PaginationCriteria;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function in_array;
use function is_array;
use function is_scalar;
use function strtolower;
use function trim;

final readonly class AdminSourceFilterQuery
{
    /** @var list<string> */
    private const array VISIBILITIES = [
        'public',
        'private',
    ];

    /** @var list<string> */
    private const array STATUSES = [
        'active',
        'inactive',
        'archived',
        'deleting',
    ];

    /** @var list<string> */
    private const array SYNC_STATUSES = [
        'pending',
        'success',
        'failed',
    ];

    /** @var list<string> */
    private const array DELETE_HEALTH = [
        'in_progress',
        'failed',
        'stale_without_job',
        'completed_but_source_exists',
        'unknown',
    ];

    /** @var list<string> */
    private const array SORT_FIELDS = [
        'name',
        'organization',
        'provider',
        'visibility',
        'logical_status',
        'last_sync_status',
        'created_at',
    ];

    /**
     * @param list<string>        $provider
     * @param list<string>        $visibility
     * @param list<string>        $status
     * @param list<string>        $syncStatus
     * @param list<string>        $deleteHealth
     * @param array<string,mixed> $filters
     */
    public function __construct(
        public PaginationCriteria $paginationCriteria,
        public array $filters,
        public string $organizationId = '',
        public string $search = '',
        public array $provider = [],
        public array $visibility = [],
        public array $status = [],
        public array $syncStatus = [],
        public array $deleteHealth = [],
        public string $sourceId = '',
        public string $url = '',
        public string $owner = '',
        public string $repository = '',
        public string $defaultRef = '',
        public string $createdFrom = '',
        public string $createdTo = '',
        public string $archivedFrom = '',
        public string $archivedTo = '',
        public string $sort = 'created_at',
        public string $direction = 'desc',
    ) {}

    /**
     * @param array<string, mixed> $queryParams
     */
    public static function fromQueryParams(array $queryParams): self
    {
        $paginationCriteria = PaginationCriteria::fromArray([
            'page'      => self::string($queryParams['page'] ?? ''),
            'per_page'  => self::string($queryParams['per_page'] ?? '20'),
            'sort'      => self::string($queryParams['sort'] ?? 'created_at'),
            'direction' => self::string($queryParams['direction'] ?? 'desc'),
            'search'    => self::string($queryParams['search'] ?? ''),
        ]);

        $organizationId = self::string($queryParams['organization_id'] ?? '');
        $search         = self::string($queryParams['search'] ?? '');
        $provider       = self::list($queryParams['provider'] ?? null);
        $visibility     = self::list($queryParams['visibility'] ?? null, self::VISIBILITIES);
        $status         = self::list($queryParams['status'] ?? null, self::STATUSES);
        $syncStatus     = self::list($queryParams['sync_status'] ?? null, self::SYNC_STATUSES);
        $deleteHealth   = self::list($queryParams['delete_health'] ?? null, self::DELETE_HEALTH);
        $sourceId       = self::string($queryParams['source_id'] ?? '');
        $url            = self::string($queryParams['url'] ?? '');
        $owner          = self::string($queryParams['owner'] ?? '');
        $repository     = self::string($queryParams['repository'] ?? '');
        $defaultRef     = self::string($queryParams['default_ref'] ?? '');
        $createdFrom    = self::string($queryParams['created_from'] ?? '');
        $createdTo      = self::string($queryParams['created_to'] ?? '');
        $archivedFrom   = self::string($queryParams['archived_from'] ?? '');
        $archivedTo     = self::string($queryParams['archived_to'] ?? '');
        $sort           = self::allowedString($paginationCriteria->sortBy ?? '', self::SORT_FIELDS) ?: 'created_at';
        $direction      = $paginationCriteria->sortDirection->value;

        return new self(
            paginationCriteria: $paginationCriteria,
            filters: [
                'organization_id' => $organizationId,
                'search'          => $search,
                'provider'        => $provider,
                'visibility'      => $visibility,
                'status'          => $status,
                'sync_status'     => $syncStatus,
                'delete_health'   => $deleteHealth,
                'source_id'       => $sourceId,
                'url'             => $url,
                'owner'           => $owner,
                'repository'      => $repository,
                'default_ref'     => $defaultRef,
                'created_from'    => $createdFrom,
                'created_to'      => $createdTo,
                'archived_from'   => $archivedFrom,
                'archived_to'     => $archivedTo,
                'sort'            => $sort,
                'direction'       => $direction,
                'page'            => $paginationCriteria->page,
                'per_page'        => $paginationCriteria->perPage,
            ],
            organizationId: $organizationId,
            search: $search,
            provider: $provider,
            visibility: $visibility,
            status: $status,
            syncStatus: $syncStatus,
            deleteHealth: $deleteHealth,
            sourceId: $sourceId,
            url: $url,
            owner: $owner,
            repository: $repository,
            defaultRef: $defaultRef,
            createdFrom: $createdFrom,
            createdTo: $createdTo,
            archivedFrom: $archivedFrom,
            archivedTo: $archivedTo,
            sort: $sort,
            direction: $direction,
        );
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param list<string> $allowList
     */
    private static function allowedString(mixed $value, array $allowList): string
    {
        $normalized = strtolower(self::string($value));

        return in_array($normalized, $allowList, true) ? $normalized : '';
    }

    /**
     * @param null|list<string> $allowList
     *
     * @return list<string>
     */
    private static function list(mixed $value, ?array $allowList = null): array
    {
        $values = is_array($value) ? $value : explode(',', (string) ($value ?? ''));

        $normalized = array_values(array_filter(
            array_map(
                static fn (mixed $item): string => is_scalar($item) ? strtolower(trim((string) $item)) : '',
                $values,
            ),
            static fn (string $item): bool => '' !== $item
                && (null === $allowList || in_array($item, $allowList, true)),
        ));

        return array_values(array_unique($normalized));
    }
}
