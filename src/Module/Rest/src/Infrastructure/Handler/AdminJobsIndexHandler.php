<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\JobResource;
use Packio\Rest\Infrastructure\Response\PaginationResponseFactory;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Worker\Api\Dto\JobFilterCriteria;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/admin/jobs', name: 'api.v1.admin.jobs.index')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[Can('admin.access')]
final readonly class AdminJobsIndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private JobApiFacadeInterface $jobApiFacade,
        private PaginationResponseFactory $paginationResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams          = $request->getQueryParams();
        $paginatedResultDto   = $this->jobApiFacade->findPaginatedAll(
            PaginationCriteria::fromArray($queryParams),
            JobFilterCriteria::fromArray($queryParams),
        );

        return $this->paginationResponseFactory->fromPaginatedResult(
            $request,
            $paginatedResultDto,
            static fn ($job): array => JobResource::fromDto($job),
        );
    }
}
