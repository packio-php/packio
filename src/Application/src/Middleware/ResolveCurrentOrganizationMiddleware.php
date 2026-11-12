<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Packio\Application\Infrastructure\Http\ApiPathMatcher;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_string;
use function trim;

final readonly class ResolveCurrentOrganizationMiddleware implements MiddlewareInterface
{
    public function __construct(private OrganizationApiFacadeInterface $organizationApiFacade) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (ApiPathMatcher::isApiRequest($request)) {
            return $handler->handle($request);
        }

        $slug         = $request->getAttribute('organization');
        $resolvedSlug = is_string($slug) ? trim($slug) : '';
        $resolvedSlug = '' !== $resolvedSlug ? $resolvedSlug : null;

        $organization = null !== $resolvedSlug
            ? $this->organizationApiFacade->findOrganizationBySlug($resolvedSlug)
            : null;

        $request = RequestContextAttributes::withOrganizationContext($request, $resolvedSlug, $organization);

        return $handler->handle($request);
    }
}
