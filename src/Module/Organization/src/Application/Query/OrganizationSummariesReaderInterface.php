<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Query;

use Packio\Organization\Api\Dto\OrganizationSummaryDto;
use Packio\Shared\Domain\Pagination\PaginationCriteria;

interface OrganizationSummariesReaderInterface
{
    /**
     * A null criteria preserves the legacy unpaginated admin collection.
     *
     * @return array{items: list<OrganizationSummaryDto>, total: int, currentPage: int, perPage: int}
     */
    public function fetch(?PaginationCriteria $paginationCriteria): array;
}
