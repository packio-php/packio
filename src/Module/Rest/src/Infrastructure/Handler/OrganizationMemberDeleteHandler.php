<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Organization\Api\Exception\OrganizationExceptionInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Domain\Organization\ActorContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Delete;

#[Delete('/api/v1/organizations/:organization/members/:memberId', name: 'api.v1.organizations.members.delete')]
#[ApiAuthenticated]
#[ApiScope('members:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_destructive', by: ['ip', 'user'], response: 'json', message: 'Too many destructive API requests. Please try again later.')]
#[Can('organizations.members.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class OrganizationMemberDeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorContext = RequestContextAttributes::currentOrganizationActorContext($request);
        if (! $actorContext instanceof ActorContext) {
            return $this->apiErrorResponseFactory->forbidden();
        }

        $memberId = ApiRouteResourceResolver::memberId($request);
        if ('' === $memberId) {
            return $this->apiErrorResponseFactory->notFound('Organization member not found.');
        }

        try {
            $removed = $this->organizationApiFacade->removeMemberInContext($actorContext, $memberId);
        } catch (OrganizationExceptionInterface $exception) {
            return $this->apiErrorResponseFactory->error(
                'membership_delete_failed',
                $exception->userMessage(),
                409,
            );
        }

        if (! $removed) {
            return $this->apiErrorResponseFactory->notFound('Organization member not found.');
        }

        return $this->apiResponseFactory->empty();
    }
}
