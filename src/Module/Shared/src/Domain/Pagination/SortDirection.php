<?php

declare(strict_types=1);

namespace Packio\Shared\Domain\Pagination;

enum SortDirection: string
{
    case ASC  = 'asc';
    case DESC = 'desc';

    /**
     * @return non-empty-string
     */
    public function sql(): string
    {
        return match ($this) {
            self::ASC  => 'ASC',
            self::DESC => 'DESC',
        };
    }
}
