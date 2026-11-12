<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\SourceResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Post('/api/v1/organizations/:organization/sources/:sourceId/webhook/rotate-secret', name: 'api.v1.organizations.sources.webhook.rotate-secret')]
#[ApiAuthenticated]
#[ApiScope('sources:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_destructive', by: ['ip', 'user'], response: 'json', message: 'Too many destructive API requests. Please try again later.')]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class SourceWebhookRotateSecretHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorContext = RequestContextAttributes::currentOrganizationActorContext($request);
        if (! $actorContext instanceof ActorContext) {
            return $this->apiErrorResponseFactory->forbidden();
        }

        $source = ApiRouteResourceResolver::source($request, $this->sourceApiFacade);
        if (! $source instanceof SourceDto) {
            return $this->apiErrorResponseFactory->notFound('Source not found.');
        }

        $rotated = $this->sourceApiFacade->rotateWebhookSecretInContext($actorContext, $source->id);
        if (! $rotated instanceof SourceDto) {
            return $this->apiErrorResponseFactory->notFound('Source not found.');
        }

        return $this->apiResponseFactory->json([
            'data' => SourceResource::fromDto($rotated),
        ]);
    }
}
