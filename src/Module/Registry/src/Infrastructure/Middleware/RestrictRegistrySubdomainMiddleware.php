<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Middleware;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Registry\Application\Service\RegistryOrganizationResolver;
use Packio\Registry\Infrastructure\ComposerRegistryResponseHeaders;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_any;
use function in_array;
use function str_starts_with;

/**
 * Redirects UI requests on the registry subdomain (e.g., acme.packio.example.com) to the
 * main application domain. Composer registry endpoints are allowed through.
 *
 * - Composer routes: /packages.json, /private/packages.json, /p2/*, /dist/* → allowed
 * - Inertia UI request → 409 with X-Inertia-Location header
 * - All other requests → 302 redirect to the app base URL
 *
 * Must be registered early in the pipeline, before session/auth middleware.
 */
final readonly class RestrictRegistrySubdomainMiddleware implements MiddlewareInterface
{
    private const array ALLOWED_PREFIXES = [
        '/packages.json',
        '/private/packages.json',
        '/p2/',
        '/dist/',
    ];

    public function __construct(
        private RegistryOrganizationResolver $registryOrganizationResolver,
        private BrandingConfig $brandingConfig
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $slug = $this->registryOrganizationResolver->slugFromRequest($request);

        // Not on a registry subdomain → pass through
        if (null === $slug) {
            return $handler->handle($request);
        }

        // On registry subdomain, check if it's an allowed Composer path
        $path = $request->getUri()->getPath();

        if ($this->isAllowedRegistryPath($path)) {
            return ComposerRegistryResponseHeaders::forPath(
                $handler->handle($request),
                $path,
            );
        }

        // Redirect to main application domain
        return $this->buildRedirectResponse($request);
    }

    private function isAllowedRegistryPath(string $path): bool
    {
        return array_any(self::ALLOWED_PREFIXES, fn ($prefix) => str_starts_with($path, (string) $prefix));
    }

    private function buildRedirectResponse(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $uri        = $serverRequest->getUri();
        $appBaseUrl = $this->brandingConfig->appBaseUrl();
        $targetPath = $uri->getPath();
        $query      = $uri->getQuery();

        $target = $appBaseUrl . $targetPath;
        if ('' !== $query) {
            $target .= '?' . $query;
        }

        // Inertia request → 409 with X-Inertia-Location
        if ($this->isInertiaRequest($serverRequest)) {
            $redirectResponse = new RedirectResponse($target, 409);

            return $redirectResponse->withHeader('X-Inertia-Location', $target);
        }

        return new RedirectResponse($target, 302);
    }

    private function isInertiaRequest(ServerRequestInterface $serverRequest): bool
    {
        return in_array(
            $serverRequest->getHeaderLine('X-Inertia'),
            ['true', '1'],
            true,
        );
    }
}
