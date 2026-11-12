<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Packio\Application\Infrastructure\Http\ApiPathMatcher;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ResolveOrganizationActorContextMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (ApiPathMatcher::isApiRequest($request)) {
            return $handler->handle($request);
        }

        $context = RequestContextAttributes::context($request);

        $actorContext = $context->currentOrganization instanceof OrganizationDto
        && $context->currentMembership instanceof OrganizationMemberDto
        && $context->currentMembership->organizationId === $context->currentOrganization->id
        && '' !== $context->currentMembership->userId
            ? new ActorContext(
                $context->currentOrganization->id,
                $context->currentMembership->userId,
                $context->currentMembership->role,
            )
            : null;

        $request = RequestContextAttributes::withOrganizationActorContext($request, $actorContext);

        return $handler->handle($request);
    }
}
