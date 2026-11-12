<?php

declare(strict_types=1);

namespace Packio\Shared\Api\Dto;

use function ceil;
use function max;
use function min;

/**
 * @template T
 */
final readonly class PaginatedResultDto
{
    /**
     * @param array<T> $items
     */
    public function __construct(public array $items, public int $total, public int $currentPage, public int $perPage) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    public function hasPreviousPages(): bool
    {
        return $this->currentPage > 1;
    }

    public function from(): int
    {
        if (0 === $this->total) {
            return 0;
        }

        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function to(): int
    {
        if (0 === $this->total) {
            return 0;
        }

        return min($this->currentPage * $this->perPage, $this->total);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items'              => $this->items,
            'total'              => $this->total,
            'current_page'       => $this->currentPage,
            'per_page'           => $this->perPage,
            'last_page'          => $this->lastPage(),
            'has_more_pages'     => $this->hasMorePages(),
            'has_previous_pages' => $this->hasPreviousPages(),
            'from'               => $this->from(),
            'to'                 => $this->to(),
        ];
    }
}
