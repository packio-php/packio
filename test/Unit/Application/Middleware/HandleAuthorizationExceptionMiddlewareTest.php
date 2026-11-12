<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\SessionInterface;
use Packio\Application\Error\AuthorizationExceptionResponseFactory;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\HandleAuthorizationExceptionMiddleware;
use Packio\Shared\Api\Dto\OrganizationDto;
use PackioTest\Support\Fake\InMemorySession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Rbac\Exception\AuthorizationException;

#[CoversClass(HandleAuthorizationExceptionMiddleware::class)]
final class HandleAuthorizationExceptionMiddlewareTest extends TestCase
{
    #[Test]
    public function itConvertsOwnerScopedAuthorizationExceptionUsingResolvedRequestContext(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = new ServerRequest()
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(
                RequestContextAttributes::CURRENT_ORGANIZATION,
                new OrganizationDto('org-1', 'acme', 'Acme'),
            )
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, new TestInertia())
        ;

        $response = $this->middleware()->process(
            $serverRequest,
            new ThrowingAuthorizationHandler(new AuthorizationException('sources.manage')),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organization/acme', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function itReturnsJsonForbiddenForApiRequests(): void
    {
        $serverRequest = new ServerRequest([], [], '/api/v1/organizations/acme/sources', 'POST');

        $response = $this->middleware()->process(
            $serverRequest,
            new ThrowingAuthorizationHandler(new AuthorizationException('sources.manage', 'Forbidden', [], 'Owner access is required.')),
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('Owner access is required.', (string) $response->getBody());
    }

    #[Test]
    public function itReturnsJsonForbiddenForInertiaRequestsOnApiPaths(): void
    {
        $serverRequest = new ServerRequest([], [], '/api/v1/organizations/acme/sources', 'POST')
            ->withHeader('X-Inertia', 'true')
        ;

        $response = $this->middleware()->process(
            $serverRequest,
            new ThrowingAuthorizationHandler(new AuthorizationException('sources.manage', 'Forbidden', [], 'Owner access is required.')),
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function itRethrowsNonOrganizationBrowserAuthorizationException(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->middleware()->process(
            new ServerRequest(),
            new ThrowingAuthorizationHandler(new AuthorizationException('admin.access')),
        );
    }

    private function middleware(): HandleAuthorizationExceptionMiddleware
    {
        return new HandleAuthorizationExceptionMiddleware(
            new AuthorizationExceptionResponseFactory(new InertiaNotificationMessenger()),
        );
    }
}

final readonly class ThrowingAuthorizationHandler implements RequestHandlerInterface
{
    public function __construct(private AuthorizationException $authorizationException) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw $this->authorizationException;
    }
}
