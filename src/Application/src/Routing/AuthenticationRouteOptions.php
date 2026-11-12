<?php

declare(strict_types=1);

namespace Packio\Application\Routing;

use Mezzio\Router\RouteResult;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function is_string;

/**
 * Route options used to select the authentication profile for a request.
 *
 * A route that declares a non-default profile must provide its authentication
 * middleware itself. This prevents the global web profile from establishing a
 * second authentication context before the route middleware runs.
 */
final class AuthenticationRouteOptions
{
    public const string PROFILE = 'authentication_profile';

    public const string API_PROFILE = 'api';

    private function __construct() {}

    public static function profile(ServerRequestInterface $serverRequest): ?string
    {
        $routeResult = $serverRequest->getAttribute(RouteResult::class);

        if (! $routeResult instanceof RouteResult || ! $routeResult->isSuccess()) {
            return null;
        }

        $route = $routeResult->getMatchedRoute();
        if (false === $route) {
            throw new UnexpectedValueException('Matched route authentication profile cannot be resolved.');
        }

        $profile = $route->getOptions()[self::PROFILE] ?? null;
        if (null === $profile) {
            return null;
        }

        if (! is_string($profile) || '' === $profile) {
            throw new UnexpectedValueException('Authentication route profile must be a non-empty string.');
        }

        return $profile;
    }
}
