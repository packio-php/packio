<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Request\UpdateSourceRequest;
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
use Sirix\Mezzio\Routing\Attributes\Attribute\Patch;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Patch('/api/v1/organizations/:organization/sources/:sourceId', name: 'api.v1.organizations.sources.update')]
#[ApiAuthenticated]
#[ApiScope('sources:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_write', by: ['ip', 'user'], response: 'json', message: 'Too many API write requests. Please try again later.')]
#[MapRequest(source: UpdateSourceRequest::class)]
#[ValidateMappedRequest(UpdateSourceRequest::class)]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class SourceUpdateHandler implements RequestHandlerInterface
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

        $sourceId = ApiRouteResourceResolver::sourceId($request);
        if ('' === $sourceId) {
            return $this->apiErrorResponseFactory->notFound('Source not found.');
        }

        /** @var UpdateSourceRequest $form */
        $form = $request->getAttribute(UpdateSourceRequest::class);

        $sourceSettingsResultDto = $this->sourceApiFacade->updateSourceSettings($actorContext, $sourceId, $form->toPatch());
        if ($sourceSettingsResultDto->isForbidden()) {
            return $this->apiErrorResponseFactory->forbidden();
        }

        if (! $sourceSettingsResultDto->source instanceof SourceDto) {
            return $this->apiErrorResponseFactory->notFound('Source not found.');
        }

        return $this->apiResponseFactory->json([
            'data' => SourceResource::fromDto($sourceSettingsResultDto->source),
        ]);
    }
}
