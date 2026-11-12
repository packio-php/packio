<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Rest\Attribute\ApiAudited;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\JobResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Post('/api/v1/admin/jobs/:jobId/restart', name: 'api.v1.admin.jobs.restart')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[ApiAudited]
#[RateLimit(key: 'api_queue_action', by: ['ip', 'user'], response: 'json', message: 'Too many API queue action requests. Please try again later.')]
#[Can('admin.access')]
final readonly class AdminJobRestartHandler implements RequestHandlerInterface
{
    public function __construct(
        private JobApiFacadeInterface $jobApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $jobId = ApiRouteResourceResolver::jobId($request);
        if ('' === $jobId) {
            return $this->apiErrorResponseFactory->notFound('Job not found.');
        }

        $adminUserId = RequestContextAttributes::currentUserId($request);
        if (null === $adminUserId || '' === $adminUserId) {
            return $this->apiErrorResponseFactory->forbidden('Admin user not resolved.');
        }

        $newJob = $this->jobApiFacade->restartAsAdmin($jobId, $adminUserId);
        if (! $newJob instanceof JobDto) {
            return $this->apiErrorResponseFactory->notFound('Job not found.');
        }

        return $this->apiResponseFactory->json([
            'data' => JobResource::fromDto($newJob),
        ], 202);
    }
}
