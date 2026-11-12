<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\SessionInterface;
use Packio\Application\Middleware\RefreshAuthenticatedSessionMiddleware;
use Packio\Identity\Infrastructure\Authentication\UserActor;
use PackioTest\Support\Base\UnitTestCase;
use PackioTest\Support\Fake\InMemorySession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\AuthenticationContext;
use Sirix\Mezzio\Authentication\Token\AuthToken;

#[CoversClass(RefreshAuthenticatedSessionMiddleware::class)]
final class RefreshAuthenticatedSessionMiddlewareTest extends UnitTestCase
{
    #[Test]
    public function itTouchesSessionForAuthenticatedRequests(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = new ServerRequest()
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(AuthenticationAttributes::Context->value, new AuthenticationContext(
                new AuthToken('token-id', 'session', [
                    'user_id' => 'user-1',
                ]),
                new UserActor('user-1', ['user']),
            ))
        ;

        new RefreshAuthenticatedSessionMiddleware()->process($serverRequest, $this->handler());

        self::assertTrue($inMemorySession->hasChanged());
        self::assertArrayHasKey('_packio_session_last_activity', $inMemorySession->toArray());
    }

    #[Test]
    public function itLeavesGuestSessionsUntouched(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = new ServerRequest()
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(AuthenticationAttributes::Context->value, new AuthenticationContext())
        ;

        new RefreshAuthenticatedSessionMiddleware()->process($serverRequest, $this->handler());

        self::assertFalse($inMemorySession->hasChanged());
        self::assertSame([], $inMemorySession->toArray());
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
