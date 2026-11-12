<?php

declare(strict_types=1);

namespace Packio\Application\Routing;

use Mezzio\Router\RouteResult;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function is_bool;

/**
 * Route options controlling whether a request is exempt from the browser CSRF
 * synchronizer-token policy.
 */
final class CsrfRouteOptions
{
    public const string EXEMPT = 'csrf_exempt';

    private function __construct() {}

    public static function isExempt(ServerRequestInterface $serverRequest): bool
    {
        $routeResult = $serverRequest->getAttribute(RouteResult::class);

        if (! $routeResult instanceof RouteResult || ! $routeResult->isSuccess()) {
            throw new UnexpectedValueException('Matched route CSRF policy cannot be resolved.');
        }

        $route = $routeResult->getMatchedRoute();
        if (false === $route) {
            throw new UnexpectedValueException('Matched route CSRF policy cannot be resolved.');
        }

        $exempt = $route->getOptions()[self::EXEMPT] ?? false;
        if (! is_bool($exempt)) {
            throw new UnexpectedValueException('CSRF route exemption must be a boolean.');
        }

        return $exempt;
    }
}
