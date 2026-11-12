<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Response;

use Packio\Shared\Api\Dto\PaginatedResultDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function ceil;
use function count;
use function http_build_query;
use function max;
use function min;

final readonly class PaginationResponseFactory
{
    public function __construct(
        private ApiResponseFactory $apiResponseFactory,
    ) {}

    /**
     * @param PaginatedResultDto<mixed>             $paginatedResultDto
     * @param callable(mixed): array<string, mixed> $map
     */
    public function fromPaginatedResult(
        ServerRequestInterface $serverRequest,
        PaginatedResultDto $paginatedResultDto,
        callable $map,
    ): ResponseInterface {
        $items = [];
        foreach ($paginatedResultDto->items as $item) {
            $items[] = $map($item);
        }

        $page    = max(1, $paginatedResultDto->currentPage);
        $perPage = max(1, $paginatedResultDto->perPage);
        $total   = max(0, $paginatedResultDto->total);
        $last    = max(1, (int) ceil($total / $perPage));
        $from    = 0 === $total ? 0 : (($page - 1) * $perPage) + 1;
        $to      = 0 === $total ? 0 : min($total, $from + count($items) - 1);

        return $this->apiResponseFactory->json([
            'data'  => $items,
            'meta'  => [
                'total'        => $total,
                'current_page' => $page,
                'per_page'     => $perPage,
                'last_page'    => $last,
                'from'         => $from,
                'to'           => $to,
            ],
            'links' => [
                'first' => $this->pageUrl($serverRequest, 1, $perPage),
                'prev'  => $page > 1 ? $this->pageUrl($serverRequest, $page - 1, $perPage) : null,
                'next'  => $page < $last ? $this->pageUrl($serverRequest, $page + 1, $perPage) : null,
                'last'  => $this->pageUrl($serverRequest, $last, $perPage),
            ],
        ]);
    }

    private function pageUrl(ServerRequestInterface $serverRequest, int $page, int $perPage): string
    {
        $query             = $serverRequest->getQueryParams();
        $query['page']     = (string) $page;
        $query['per_page'] = (string) $perPage;

        return (string) $serverRequest->getUri()->withQuery(http_build_query($query));
    }
}
