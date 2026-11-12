<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Request\ReleaseActivityRequest;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\ReleaseResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Domain\Organization\ActorContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/api/v1/organizations/:organization/packages/:vendor/:package/releases/by-version/activity', name: 'api.v1.organizations.packages.releases.activity')]
#[ApiAuthenticated]
#[ApiScope('releases:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_write', by: ['ip', 'user'], response: 'json', message: 'Too many API write requests. Please try again later.')]
#[MapRequest(source: ReleaseActivityRequest::class)]
#[ValidateMappedRequest(ReleaseActivityRequest::class)]
#[Can('packages.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class ReleaseActivityHandler implements RequestHandlerInterface
{
    public function __construct(
        private ReleaseApiFacadeInterface $releaseApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorContext = RequestContextAttributes::currentOrganizationActorContext($request);
        if (! $actorContext instanceof ActorContext) {
            return $this->apiErrorResponseFactory->forbidden();
        }

        /** @var ReleaseActivityRequest $form */
        $form   = $request->getAttribute(ReleaseActivityRequest::class);

        /** @var bool $active Validated by ValidateMappedRequest. */
        $active = $form->active();

        $release = $this->releaseApiFacade->updateActivityInContext(
            $actorContext,
            ApiRouteResourceResolver::packageName($request),
            $form->version(),
            $active,
        );
        if (! $release instanceof ReleaseDto) {
            return $this->apiErrorResponseFactory->notFound('Release not found.');
        }

        return $this->apiResponseFactory->json([
            'data' => ReleaseResource::fromDto($release),
        ]);
    }
}
