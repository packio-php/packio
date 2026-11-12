<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Common\Logging\SafeExceptionContext;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Throwable;

#[Post('/api/v1/organizations/:organization/sources/:sourceId/sync', name: 'api.v1.organizations.sources.sync')]
#[ApiAuthenticated]
#[ApiScope('sources:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_queue_action', by: ['ip', 'user'], response: 'json', message: 'Too many API queue action requests. Please try again later.')]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class SourceSyncHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
        private ?LoggerInterface $logger = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $source = ApiRouteResourceResolver::source($request, $this->sourceApiFacade);
        if (! $source instanceof SourceDto) {
            return $this->apiErrorResponseFactory->notFound('Source not found.');
        }

        if (! $source->active || $source->archived) {
            return $this->apiErrorResponseFactory->error(
                'source_inactive',
                'Source must be active before scheduling a sync.',
                409,
            );
        }

        try {
            $this->sourceApiFacade->scheduleSourceSync($source->id, $source->organizationId, 5);
        } catch (Throwable $exception) {
            $this->logger?->error('Source sync scheduling failed.', SafeExceptionContext::forRequest($exception, $request) + [
                'organization_id' => $source->organizationId,
                'source_id'       => $source->id,
                'provider'        => $source->provider,
            ]);

            return $this->apiErrorResponseFactory->error(
                'source_sync_not_scheduled',
                'Source sync could not be scheduled.',
                500,
            );
        }

        return $this->apiResponseFactory->json([
            'data' => [
                'queued'    => true,
                'source_id' => $source->id,
            ],
        ], 202);
    }
}
