<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Attribute;

use Packio\Application\Routing\AuthenticationRouteOptions;
use Packio\Application\Routing\CsrfRouteOptions;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Infrastructure\Middleware\ApiAuthenticationContextMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Factory\ProfileMiddlewareFactory;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

#[CoversClass(ApiAuthenticated::class)]
final class ApiAuthenticatedTest extends TestCase
{
    #[Test]
    public function selectsTheApiProfileBeforeApplyingTheApiContextPolicy(): void
    {
        $apiAuthenticated  = new ApiAuthenticated();
        $middleware        = $apiAuthenticated->getMiddleware();

        self::assertCount(2, $middleware);
        self::assertInstanceOf(MiddlewareSpecification::class, $middleware[0]);
        self::assertSame(OptionalAuthenticateMiddleware::class, $middleware[0]->service);
        self::assertSame(ProfileMiddlewareFactory::class, $middleware[0]->factory);
        self::assertSame([
            'profile' => AuthenticationRouteOptions::API_PROFILE,
        ], $middleware[0]->arguments);
        self::assertSame(ApiAuthenticationContextMiddleware::class, $middleware[1]);
    }

    #[Test]
    public function declaresTheApiAuthenticationProfileAsARouteDefault(): void
    {
        self::assertSame([
            AuthenticationRouteOptions::PROFILE => AuthenticationRouteOptions::API_PROFILE,
            CsrfRouteOptions::EXEMPT            => true,
        ], new ApiAuthenticated()->getDefaults());
    }
}
