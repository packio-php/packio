<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\JobLogResource;
use Packio\Rest\Infrastructure\Resource\JobResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/admin/jobs/:jobId', name: 'api.v1.admin.jobs.show')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[Can('admin.access')]
final readonly class AdminJobShowHandler implements RequestHandlerInterface
{
    public function __construct(
        private JobApiFacadeInterface $jobApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $jobId = ApiRouteResourceResolver::jobId($request);
        $job   = '' !== $jobId ? $this->jobApiFacade->findById($jobId) : null;
        if (! $job instanceof JobDto) {
            return $this->apiErrorResponseFactory->notFound('Job not found.');
        }

        $logs = [];
        foreach ($this->jobApiFacade->findLogsByJob($job->id) as $jobLogDto) {
            $logs[] = JobLogResource::fromDto($jobLogDto);
        }

        return $this->apiResponseFactory->json([
            'data' => JobResource::fromDto($job),
            'logs' => $logs,
        ]);
    }
}
