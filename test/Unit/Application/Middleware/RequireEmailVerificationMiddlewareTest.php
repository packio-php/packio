<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireEmailVerificationMiddleware;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\UnitTestCase;
use PackioTest\Support\Fake\InMemorySession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(RequireEmailVerificationMiddleware::class)]
final class RequireEmailVerificationMiddlewareTest extends UnitTestCase
{
    #[Test]
    public function itAllowsAnUnverifiedBannedUserToReachTheBannedAccountPageWithoutChangingVerificationSessionState(): void
    {
        $inMemorySession  = new InMemorySession();
        $response         = new RequireEmailVerificationMiddleware()->process(
            new ServerRequest(uri: '/account/banned')
                ->withAttribute('session', $inMemorySession)
                ->withAttribute(RequestContextAttributes::CURRENT_USER, $this->unverifiedBannedUser()),
            $this->handler(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $inMemorySession->toArray());
    }

    #[Test]
    public function itStillRedirectsAnUnverifiedUserFromOtherPagesAndStoresTheVerificationDestination(): void
    {
        $inMemorySession  = new InMemorySession();
        $response         = new RequireEmailVerificationMiddleware()->process(
            new ServerRequest(uri: '/organizations')
                ->withAttribute('session', $inMemorySession)
                ->withAttribute(RequestContextAttributes::CURRENT_USER, $this->unverifiedBannedUser()),
            $this->handler(),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/verification', $response->getHeaderLine('Location'));
        self::assertSame('user-1', $inMemorySession->get('pending_verification_user_uuid'));
        self::assertSame('/organizations', $inMemorySession->get('pending_verification_redirect_to'));
    }

    private function unverifiedBannedUser(): UserDto
    {
        return new UserDto(
            'user-1',
            'user@example.test',
            'Banned User',
            ['user'],
            '2026-08-22T00:00:00+00:00',
            isEmailVerified: false,
            isBanned: true,
        );
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
