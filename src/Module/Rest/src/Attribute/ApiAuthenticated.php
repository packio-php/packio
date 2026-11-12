<?php

declare(strict_types=1);

namespace Packio\Rest\Attribute;

use Attribute;
use Packio\Application\Routing\AuthenticationRouteOptions;
use Packio\Application\Routing\CsrfRouteOptions;
use Packio\Rest\Infrastructure\Middleware\ApiAuthenticationContextMiddleware;
use Sirix\Mezzio\Authentication\Factory\ProfileMiddlewareFactory;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class ApiAuthenticated implements RouteAttributeModifierInterface
{
    public function getMiddleware(): array
    {
        return [
            new MiddlewareSpecification(
                OptionalAuthenticateMiddleware::class,
                ProfileMiddlewareFactory::class,
                [
                    'profile' => AuthenticationRouteOptions::API_PROFILE,
                ],
            ),
            ApiAuthenticationContextMiddleware::class,
        ];
    }

    public function getDefaults(): array
    {
        return [
            AuthenticationRouteOptions::PROFILE => AuthenticationRouteOptions::API_PROFILE,
            CsrfRouteOptions::EXEMPT            => true,
        ];
    }
}
