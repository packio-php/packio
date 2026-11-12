<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Middleware;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;

use function is_string;
use function trim;

final readonly class ApiOrganizationContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $organizationIdentifier = $request->getAttribute('organization');
        $organizationIdentifier = is_string($organizationIdentifier) ? trim($organizationIdentifier) : '';
        if ('' === $organizationIdentifier) {
            return $this->apiErrorResponseFactory->notFound('Organization not found.');
        }

        $organization = $this->organizationApiFacade->findOrganizationBySlug($organizationIdentifier)
            ?? (Uuid::isValid($organizationIdentifier) ? $this->organizationApiFacade->findOrganization(Uuid::fromString($organizationIdentifier)->toString()) : null);
        if (! $organization instanceof OrganizationDto) {
            return $this->apiErrorResponseFactory->notFound('Organization not found.');
        }

        $membership    = null;
        $currentUserId = RequestContextAttributes::currentUserId($request);
        if (null !== $currentUserId && '' !== $currentUserId) {
            $membership = $this->organizationApiFacade->findMembership($organization->id, $currentUserId);
        }

        $actorContext = $membership instanceof OrganizationMemberDto
            ? new ActorContext($organization->id, $membership->userId, $membership->role)
            : null;

        $request = RequestContextAttributes::withOrganizationContext(
            $request,
            $organization->slug,
            $organization,
            $membership,
            $actorContext,
        );

        return $handler->handle($request);
    }
}
