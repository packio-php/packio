<?php

declare(strict_types=1);

namespace Packio\Application\Router;

use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Router\RadixRouter;

/**
 * Restores the failed HEAD match expected by Mezzio's ImplicitHeadMiddleware.
 *
 * RadixRouter accepts HEAD for GET routes itself, which prevents the native
 * middleware from forwarding the request as GET and removing the response body.
 */
final readonly class ImplicitHeadRouterAdapter implements RouterInterface
{
    public function __construct(private RadixRouter $radixRouter) {}

    public function addRoute(Route $route): void
    {
        $this->radixRouter->addRoute($route);
    }

    public function match(ServerRequestInterface $request): RouteResult
    {
        $routeResult = $this->radixRouter->match($request);
        if ('HEAD' !== $request->getMethod() || $routeResult->isFailure()) {
            return $routeResult;
        }

        $route = $routeResult->getMatchedRoute();
        if (false !== $route && $route->allowsMethod('GET') && ! $route->allowsMethod('HEAD')) {
            return RouteResult::fromRouteFailure($routeResult->getAllowedMethods());
        }

        return $routeResult;
    }

    /**
     * @param array<string, string> $substitutions
     * @param array<string, mixed>  $options
     */
    public function generateUri(string $name, array $substitutions = [], array $options = []): string
    {
        return $this->radixRouter->generateUri($name, $substitutions, $options);
    }
}
