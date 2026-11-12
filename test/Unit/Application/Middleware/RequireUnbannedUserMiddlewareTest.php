<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireUnbannedUserMiddleware;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(RequireUnbannedUserMiddleware::class)]
final class RequireUnbannedUserMiddlewareTest extends UnitTestCase
{
    #[Test]
    public function itRedirectsABannedUserFromProtectedWebRoutes(): void
    {
        $response = $this->middleware()->process(
            $this->bannedRequest('/profile'),
            $this->handler(),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/account/banned', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function itAllowsOnlyTheBannedPageAndLogoutForABannedUser(): void
    {
        self::assertSame(200, $this->middleware()->process(
            $this->bannedRequest('/account/banned'),
            $this->handler(),
        )->getStatusCode());
        self::assertSame(200, $this->middleware()->process(
            $this->bannedRequest('/auth/logout', 'POST'),
            $this->handler(),
        )->getStatusCode());
    }

    private function middleware(): RequireUnbannedUserMiddleware
    {
        return new RequireUnbannedUserMiddleware();
    }

    private function bannedRequest(string $uri, string $method = 'GET'): ServerRequest
    {
        return new ServerRequest(uri: $uri, method: $method)
            ->withAttribute(RequestContextAttributes::CURRENT_USER, new UserDto(
                id: 'user-1',
                email: 'banned@example.test',
                displayName: 'Banned User',
                roles: ['user'],
                createdAt: '2026-08-22T00:00:00+00:00',
                isBanned: true,
            ))
        ;
    }

    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };
    }
}
