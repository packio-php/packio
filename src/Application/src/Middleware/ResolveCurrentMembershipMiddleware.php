<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Packio\Application\Infrastructure\Http\ApiPathMatcher;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ResolveCurrentMembershipMiddleware implements MiddlewareInterface
{
    public function __construct(private OrganizationApiFacadeInterface $organizationApiFacade) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (ApiPathMatcher::isApiRequest($request)) {
            return $handler->handle($request);
        }

        $context      = RequestContextAttributes::context($request);
        $organization = $context->currentOrganization;
        $userId       = $context->currentUserId;

        $membership = $organization instanceof OrganizationDto && null !== $userId && '' !== $userId
            ? $this->organizationApiFacade->findMembership($organization->id, $userId)
            : null;

        $request = RequestContextAttributes::withCurrentMembership($request, $membership);

        return $handler->handle($request);
    }
}
