<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Request;

use Packio\Shared\Domain\Pagination\PaginationCriteria;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function filter_var;
use function in_array;
use function is_array;
use function is_scalar;
use function strtolower;
use function trim;

final readonly class AdminPackageFilterQuery
{
    /** @var list<string> */
    private const array VISIBILITIES = [
        'public',
        'private',
    ];

    /** @var list<string> */
    private const array LISTED = [
        'listed',
        'hidden',
    ];

    /** @var list<string> */
    private const array SOURCE = [
        'managed',
        'manual',
    ];

    /** @var list<string> */
    private const array SYNC_STATUSES = [
        'pending',
        'success',
        'failed',
    ];

    /** @var list<string> */
    private const array SORT_FIELDS = [
        'name',
        'organization',
        'visibility',
        'active',
        'download_count',
        'source',
        'created_at',
        'updated_at',
    ];

    /**
     * @param list<string>        $visibility
     * @param list<string>        $sourceProvider
     * @param list<string>        $sourceSyncStatus
     * @param array<string,mixed> $filters
     */
    public function __construct(
        public PaginationCriteria $paginationCriteria,
        public array $filters,
        public string $organizationId = '',
        public string $search = '',
        public array $visibility = [],
        public string $listed = '',
        public string $source = '',
        public array $sourceProvider = [],
        public array $sourceSyncStatus = [],
        public string $packageName = '',
        public string $sourceId = '',
        public string $sourceUrl = '',
        public string $createdFrom = '',
        public string $createdTo = '',
        public string $updatedFrom = '',
        public string $updatedTo = '',
        public ?int $minDownloads = null,
        public ?int $maxDownloads = null,
        public string $sort = 'updated_at',
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
            'sort'      => self::string($queryParams['sort'] ?? 'updated_at'),
            'direction' => self::string($queryParams['direction'] ?? 'desc'),
            'search'    => self::string($queryParams['search'] ?? ''),
        ]);

        $organizationId   = self::string($queryParams['organization_id'] ?? '');
        $search           = self::string($queryParams['search'] ?? '');
        $visibility       = self::list($queryParams['visibility'] ?? null, self::VISIBILITIES);
        $listed           = self::allowedString($queryParams['listed'] ?? null, self::LISTED);
        $source           = self::allowedString($queryParams['source'] ?? null, self::SOURCE);
        $sourceProvider   = self::list($queryParams['source_provider'] ?? null);
        $sourceSyncStatus = self::list($queryParams['source_sync_status'] ?? null, self::SYNC_STATUSES);
        $packageName      = self::string($queryParams['package_name'] ?? '');
        $sourceId         = self::string($queryParams['source_id'] ?? '');
        $sourceUrl        = self::string($queryParams['source_url'] ?? '');
        $createdFrom      = self::string($queryParams['created_from'] ?? '');
        $createdTo        = self::string($queryParams['created_to'] ?? '');
        $updatedFrom      = self::string($queryParams['updated_from'] ?? '');
        $updatedTo        = self::string($queryParams['updated_to'] ?? '');
        $minDownloads     = self::intOrNull($queryParams['min_downloads'] ?? null);
        $maxDownloads     = self::intOrNull($queryParams['max_downloads'] ?? null);
        $sort             = self::allowedString($paginationCriteria->sortBy ?? '', self::SORT_FIELDS) ?: 'updated_at';
        $direction        = $paginationCriteria->sortDirection->value;

        return new self(
            paginationCriteria: $paginationCriteria,
            filters: [
                'organization_id'    => $organizationId,
                'search'             => $search,
                'visibility'         => $visibility,
                'listed'             => $listed,
                'source'             => $source,
                'source_provider'    => $sourceProvider,
                'source_sync_status' => $sourceSyncStatus,
                'package_name'       => $packageName,
                'source_id'          => $sourceId,
                'source_url'         => $sourceUrl,
                'created_from'       => $createdFrom,
                'created_to'         => $createdTo,
                'updated_from'       => $updatedFrom,
                'updated_to'         => $updatedTo,
                'min_downloads'      => $minDownloads ?? '',
                'max_downloads'      => $maxDownloads ?? '',
                'sort'               => $sort,
                'direction'          => $direction,
                'page'               => $paginationCriteria->page,
                'per_page'           => $paginationCriteria->perPage,
            ],
            organizationId: $organizationId,
            search: $search,
            visibility: $visibility,
            listed: $listed,
            source: $source,
            sourceProvider: $sourceProvider,
            sourceSyncStatus: $sourceSyncStatus,
            packageName: $packageName,
            sourceId: $sourceId,
            sourceUrl: $sourceUrl,
            createdFrom: $createdFrom,
            createdTo: $createdTo,
            updatedFrom: $updatedFrom,
            updatedTo: $updatedTo,
            minDownloads: $minDownloads,
            maxDownloads: $maxDownloads,
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

    private static function intOrNull(mixed $value): ?int
    {
        $normalized = self::string($value);
        if ('' === $normalized) {
            return null;
        }

        $int = filter_var($normalized, FILTER_VALIDATE_INT);

        return false !== $int && $int >= 0 ? $int : null;
    }
}
