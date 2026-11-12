<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Middleware;

use Packio\Application\Infrastructure\Http\ApiPathMatcher;
use Packio\Registry\Application\Service\RegistryOrganizationResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_string;
use function preg_match;
use function trim;

/**
 * Resolves the organization slug from:
 * 1. An existing route attribute `organization` (e.g., from `/organization/:slug/...`).
 * 2. The path pattern `/organization/:slug/...`.
 * 3. The registry subdomain host (e.g., `acme.packio.example.com` -> `acme`).
 *
 * Sets the `organization` attribute on the request so that
 * ResolveCurrentOrganizationMiddleware can resolve the full OrganizationDto.
 *
 * Must be registered in the pipeline BEFORE ResolveCurrentOrganizationMiddleware.
 */
final readonly class ResolveRegistryOrganizationAttributeMiddleware implements MiddlewareInterface
{
    public function __construct(private RegistryOrganizationResolver $registryOrganizationResolver) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (ApiPathMatcher::isApiRequest($request)) {
            return $handler->handle($request);
        }

        $existing = $request->getAttribute('organization');

        // 1. Already resolved (e.g., from a route with {organization} parameter)
        if (is_string($existing) && '' !== trim($existing)) {
            return $handler->handle($request);
        }

        $slug = $this->resolveFromPath($request);

        $slug ??= $this->resolveFromHost($request);

        if (null !== $slug) {
            $request = $request->withAttribute('organization', $slug);
        }

        return $handler->handle($request);
    }

    private function resolveFromPath(ServerRequestInterface $serverRequest): ?string
    {
        $path = $serverRequest->getUri()->getPath();

        if (preg_match('#^/organization/(?P<slug>[a-zA-Z0-9\-_]+)(?:/|$)#', $path, $matches)) {
            return $matches['slug'];
        }

        return null;
    }

    private function resolveFromHost(ServerRequestInterface $serverRequest): ?string
    {
        return $this->registryOrganizationResolver->slugFromRequest($serverRequest);
    }
}
