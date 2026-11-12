<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Organization\Api\Dto\OrganizationInvitationDto;
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

#[Delete('/api/v1/organizations/:organization/invitations/:invitationId', name: 'api.v1.organizations.invitations.delete')]
#[ApiAuthenticated]
#[ApiScope('members:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_destructive', by: ['ip', 'user'], response: 'json', message: 'Too many destructive API requests. Please try again later.')]
#[Can('organizations.invitations.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class OrganizationInvitationsDeleteHandler implements RequestHandlerInterface
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

        $invitationId = ApiRouteResourceResolver::invitationId($request);
        if ('' === $invitationId) {
            return $this->apiErrorResponseFactory->notFound('Invitation not found.');
        }

        $invitation = $this->invitation($actorContext->organizationId, $invitationId);
        if (! $invitation instanceof OrganizationInvitationDto) {
            return $this->apiErrorResponseFactory->notFound('Invitation not found.');
        }

        if ('pending' === $invitation->status) {
            return $this->apiErrorResponseFactory->error(
                'invitation_pending',
                'Pending invitations must be revoked before they can be deleted.',
                409,
            );
        }

        if (! $this->organizationApiFacade->deleteInvitationInContext($actorContext, $invitationId)) {
            return $this->apiErrorResponseFactory->notFound('Invitation not found.');
        }

        return $this->apiResponseFactory->empty();
    }

    private function invitation(string $organizationId, string $invitationId): ?OrganizationInvitationDto
    {
        foreach ($this->organizationApiFacade->invitations($organizationId) as $organizationInvitationDto) {
            if ($organizationInvitationDto->id === $invitationId) {
                return $organizationInvitationDto;
            }
        }

        return null;
    }
}
