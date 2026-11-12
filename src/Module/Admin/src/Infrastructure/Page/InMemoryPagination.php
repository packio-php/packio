<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Page;

use Packio\Shared\Api\Dto\PaginatedResultDto;
use Packio\Shared\Domain\Pagination\PaginationCriteria;

use function array_slice;
use function ceil;
use function count;
use function max;
use function min;

final class InMemoryPagination
{
    /**
     * @param list<array<string, mixed>> $items
     *
     * @return PaginatedResultDto<array<string, mixed>>
     */
    public static function paginate(array $items, PaginationCriteria $paginationCriteria): PaginatedResultDto
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
}
