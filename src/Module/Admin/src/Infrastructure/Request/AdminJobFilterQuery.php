<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Request;

use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Worker\Api\Dto\JobFilterCriteria;

final readonly class AdminJobFilterQuery
{
    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public PaginationCriteria $paginationCriteria,
        public JobFilterCriteria $jobFilterCriteria,
        public array $filters,
    ) {}

    /**
     * @param array<string, mixed> $queryParams
     */
    public static function fromQueryParams(array $queryParams): self
    {
        $paginationCriteria = PaginationCriteria::fromArray([
            'page'      => (string) ($queryParams['page'] ?? ''),
            'per_page'  => (string) ($queryParams['per_page'] ?? ''),
            'sort'      => (string) ($queryParams['sort'] ?? ''),
            'direction' => (string) ($queryParams['direction'] ?? ''),
            'search'    => (string) ($queryParams['search'] ?? ''),
        ]);

        $jobFilterCriteria = JobFilterCriteria::fromArray($queryParams);

        return new self(
            paginationCriteria: $paginationCriteria,
            jobFilterCriteria: $jobFilterCriteria,
            filters: [
                ...$jobFilterCriteria->toQueryArray(),
                'sort'      => $paginationCriteria->sortBy ?? 'createdAt',
                'direction' => $paginationCriteria->sortDirection->value,
                'page'      => $paginationCriteria->page,
                'per_page'  => $paginationCriteria->perPage,
            ],
        );
    }
}
