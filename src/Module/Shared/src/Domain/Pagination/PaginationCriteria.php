<?php

declare(strict_types=1);

namespace Packio\Shared\Domain\Pagination;

use InvalidArgumentException;

use function filter_var;
use function intdiv;
use function is_string;
use function min;
use function sprintf;
use function strtolower;

/**
 * Immutable value object for pagination criteria.
 */
final readonly class PaginationCriteria
{
    public const int DEFAULT_PAGE     = 1;
    public const int DEFAULT_PER_PAGE = 20;
    public const int MAX_PER_PAGE     = 100;

    public function __construct(
        public int $page = self::DEFAULT_PAGE,
        public int $perPage = self::DEFAULT_PER_PAGE,
        public ?string $sortBy = null,
        public SortDirection $sortDirection = SortDirection::DESC,
        public ?string $search = null,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('Page must be at least 1.');
        }

        if ($perPage < 1) {
            throw new InvalidArgumentException('Per page must be at least 1.');
        }

        if ($perPage > self::MAX_PER_PAGE) {
            throw new InvalidArgumentException(
                sprintf('Per page cannot exceed %d.', self::MAX_PER_PAGE)
            );
        }

        if ($page > intdiv(PHP_INT_MAX, $perPage) + 1) {
            throw new InvalidArgumentException('Page is too large for the requested page size.');
        }
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function limit(): int
    {
        return $this->perPage;
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function fromArray(array $params): self
    {
        $page = filter_var($params['page'] ?? self::DEFAULT_PAGE, FILTER_VALIDATE_INT);
        $page = false !== $page && $page >= 1 ? $page : self::DEFAULT_PAGE;

        $perPage = filter_var($params['per_page'] ?? self::DEFAULT_PER_PAGE, FILTER_VALIDATE_INT);
        $perPage = false !== $perPage && $perPage >= 1 ? $perPage : self::DEFAULT_PER_PAGE;
        $perPage = min($perPage, self::MAX_PER_PAGE);

        if ($page > intdiv(PHP_INT_MAX, $perPage) + 1) {
            $page = self::DEFAULT_PAGE;
        }

        $sortBy = isset($params['sort']) && is_string($params['sort']) && '' !== $params['sort']
            ? $params['sort']
            : null;

        $direction = isset($params['direction']) && is_string($params['direction'])
            ? SortDirection::tryFrom(strtolower($params['direction'])) ?? SortDirection::DESC
            : SortDirection::DESC;

        $search = isset($params['search']) && is_string($params['search']) && '' !== $params['search']
            ? $params['search']
            : null;

        return new self(
            page: $page,
            perPage: $perPage,
            sortBy: $sortBy,
            sortDirection: $direction,
            search: $search,
        );
    }

    public function withPage(int $page): self
    {
        return new self(
            page: $page,
            perPage: $this->perPage,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
            search: $this->search,
        );
    }
}
