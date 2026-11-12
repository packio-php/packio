<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\SessionInterface;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RequestContextDto;
use Packio\Application\Middleware\ResolveCurrentMembershipMiddleware;
use Packio\Application\Middleware\ResolveCurrentOrganizationMiddleware;
use Packio\Application\Middleware\ResolveCurrentUserMiddleware;
use Packio\Application\Middleware\ResolveOrganizationActorContextMiddleware;
use Packio\Application\Security\Authentication\CsrfAwareAuthManager;
use Packio\Application\Security\Csrf\CsrfTokenContract;
use Packio\Application\Security\Csrf\CsrfTokenManager;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Infrastructure\Authentication\UserActor;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Fake\InMemorySession;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;

#[CoversClass(ResolveCurrentUserMiddleware::class)]
#[CoversClass(ResolveCurrentOrganizationMiddleware::class)]
#[CoversClass(ResolveCurrentMembershipMiddleware::class)]
#[CoversClass(ResolveOrganizationActorContextMiddleware::class)]
#[AllowMockObjectsWithoutExpectations]
final class RequestContextMiddlewareTest extends TestCase
{
    #[Test]
    public function resolveCurrentUserMiddlewareStoresUserAndContextAttributes(): void
    {
        $userDto     = new UserDto('user-1', 'user@example.com', 'User', ['user'], '2026-01-01T00:00:00+00:00');
        $identityApi = $this->createMock(IdentityApiFacadeInterface::class);
        $identityApi->expects(self::once())
            ->method('currentUser')
            ->willReturn($userDto)
        ;

        $authManager = $this->createMock(AuthManagerInterface::class);
        $authManager->expects(self::never())->method('check');

        $resolveCurrentUserMiddleware = new ResolveCurrentUserMiddleware($identityApi, $authManager);
        $capturingHandler             = new CapturingHandler();

        $resolveCurrentUserMiddleware->process(new ServerRequest(), $capturingHandler);

        $request = $capturingHandler->lastRequest;
        self::assertNotNull($request);
        self::assertSame($userDto, $request->getAttribute(RequestContextAttributes::CURRENT_USER));
        self::assertSame('user-1', $request->getAttribute(RequestContextAttributes::CURRENT_USER_ID));

        $context = $request->getAttribute(RequestContextAttributes::CONTEXT);
        self::assertInstanceOf(RequestContextDto::class, $context);
        self::assertSame('user-1', $context->currentUserId);
        self::assertSame($userDto, $context->currentUser);
    }

    #[Test]
    public function resolveCurrentUserMiddlewareUsesCurrentUserFromLocalAuthActor(): void
    {
        $userDto     = new UserDto('user-1', 'user@example.com', 'User', ['user'], '2026-01-01T00:00:00+00:00');
        $identityApi = $this->createMock(IdentityApiFacadeInterface::class);
        $identityApi->expects(self::never())->method('currentUser');

        $authManager = $this->createMock(AuthManagerInterface::class);
        $authManager->expects(self::never())->method('check');

        $resolveCurrentUserMiddleware = new ResolveCurrentUserMiddleware($identityApi, $authManager);
        $capturingHandler             = new CapturingHandler();
        $serverRequest                = new ServerRequest()->withAttribute(
            AuthenticationAttributes::Actor->value,
            new UserActor('user-1', ['user'], $userDto),
        );

        $resolveCurrentUserMiddleware->process($serverRequest, $capturingHandler);

        $request = $capturingHandler->lastRequest;
        self::assertNotNull($request);
        self::assertSame($userDto, $request->getAttribute(RequestContextAttributes::CURRENT_USER));
        self::assertSame('user-1', $request->getAttribute(RequestContextAttributes::CURRENT_USER_ID));
    }

    #[Test]
    public function resolveCurrentUserMiddlewareLogsOutWhenAuthenticatedUserCannotBeResolved(): void
    {
        $identityApi = $this->createMock(IdentityApiFacadeInterface::class);
        $identityApi->expects(self::once())
            ->method('currentUser')
            ->willReturn(null)
        ;

        $authManager = $this->createMock(AuthManagerInterface::class);
        $authManager->expects(self::once())
            ->method('check')
            ->willReturn(true)
        ;
        $authManager->expects(self::once())
            ->method('logout')
            ->willReturnCallback(static fn (ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface => $response)
        ;

        $resolveCurrentUserMiddleware = new ResolveCurrentUserMiddleware($identityApi, $authManager);
        $capturingHandler             = new CapturingHandler();

        $response = $resolveCurrentUserMiddleware->process(new ServerRequest(), $capturingHandler);

        self::assertNull($capturingHandler->lastRequest);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function resolveCurrentUserMiddlewareClearsCsrfThroughTheAuthManagerDecoratorOnLogout(): void
    {
        $identityApi = $this->createMock(IdentityApiFacadeInterface::class);
        $identityApi->expects(self::once())->method('currentUser')->willReturn(null);

        $inMemorySession = new InMemorySession();
        $inMemorySession->set(CsrfTokenContract::SESSION_KEY, 'token');
        $request = new ServerRequest()->withAttribute(SessionInterface::class, $inMemorySession);

        $innerAuthManager = $this->createMock(AuthManagerInterface::class);
        $innerAuthManager->expects(self::once())->method('check')->with($request)->willReturn(true);
        $innerAuthManager->expects(self::once())
            ->method('logout')
            ->with($request, self::isInstanceOf(ResponseInterface::class))
            ->willReturnCallback(static fn (ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface => $response)
        ;

        $resolveCurrentUserMiddleware = new ResolveCurrentUserMiddleware(
            $identityApi,
            new CsrfAwareAuthManager($innerAuthManager, new CsrfTokenManager()),
        );

        $response = $resolveCurrentUserMiddleware->process($request, new CapturingHandler());

        self::assertSame(302, $response->getStatusCode());
        self::assertFalse($inMemorySession->has(CsrfTokenContract::SESSION_KEY));
    }

    #[Test]
    public function resolveCurrentOrganizationMiddlewareStoresOrganizationAndSlug(): void
    {
        $organizationDto = new OrganizationDto('org-1', 'acme', 'Acme');
        $organizationApi = $this->createMock(OrganizationApiFacadeInterface::class);
        $organizationApi->expects(self::once())
            ->method('findOrganizationBySlug')
            ->with('acme')
            ->willReturn($organizationDto)
        ;

        $resolveCurrentOrganizationMiddleware = new ResolveCurrentOrganizationMiddleware($organizationApi);
        $capturingHandler                     = new CapturingHandler();
        $serverRequest                        = new ServerRequest()->withAttribute('organization', 'acme');

        $resolveCurrentOrganizationMiddleware->process($serverRequest, $capturingHandler);

        $resolvedRequest = $capturingHandler->lastRequest;
        self::assertNotNull($resolvedRequest);
        self::assertSame('acme', $resolvedRequest->getAttribute(RequestContextAttributes::CURRENT_ORGANIZATION_SLUG));
        self::assertSame($organizationDto, $resolvedRequest->getAttribute(RequestContextAttributes::CURRENT_ORGANIZATION));

        $context = $resolvedRequest->getAttribute(RequestContextAttributes::CONTEXT);
        self::assertInstanceOf(RequestContextDto::class, $context);
        self::assertSame('acme', $context->currentOrganizationSlug);
        self::assertSame($organizationDto, $context->currentOrganization);
    }

    #[Test]
    public function resolveCurrentMembershipMiddlewareStoresMembershipIntoContext(): void
    {
        $organizationDto       = new OrganizationDto('org-1', 'acme', 'Acme');
        $organizationMemberDto = new OrganizationMemberDto('membership-1', 'org-1', 'user-1', 'owner');
        $organizationApi       = $this->createMock(OrganizationApiFacadeInterface::class);
        $organizationApi->expects(self::once())
            ->method('findMembership')
            ->with('org-1', 'user-1')
            ->willReturn($organizationMemberDto)
        ;

        $serverRequest = new ServerRequest()
            ->withAttribute(
                RequestContextAttributes::CONTEXT,
                RequestContextDto::empty()
                    ->withCurrentUser(new UserDto('user-1', 'user@example.com', 'User', ['user'], '2026-01-01T00:00:00+00:00'))
                    ->withCurrentOrganization('acme', $organizationDto),
            )
        ;

        $resolveCurrentMembershipMiddleware = new ResolveCurrentMembershipMiddleware($organizationApi);
        $capturingHandler                   = new CapturingHandler();

        $resolveCurrentMembershipMiddleware->process($serverRequest, $capturingHandler);

        $resolvedRequest = $capturingHandler->lastRequest;
        self::assertNotNull($resolvedRequest);
        self::assertSame($organizationMemberDto, $resolvedRequest->getAttribute(RequestContextAttributes::CURRENT_MEMBERSHIP));

        $context = $resolvedRequest->getAttribute(RequestContextAttributes::CONTEXT);
        self::assertInstanceOf(RequestContextDto::class, $context);
        self::assertSame($organizationMemberDto, $context->currentMembership);
    }

    #[Test]
    public function resolveOrganizationActorContextMiddlewareStoresActorContext(): void
    {
        $organizationDto       = new OrganizationDto('org-1', 'acme', 'Acme');
        $organizationMemberDto = new OrganizationMemberDto('membership-1', 'org-1', 'user-1', 'owner');
        $serverRequest         = new ServerRequest()
            ->withAttribute(
                RequestContextAttributes::CONTEXT,
                RequestContextDto::empty()
                    ->withCurrentOrganization('acme', $organizationDto)
                    ->withCurrentMembership($organizationMemberDto),
            )
        ;

        $resolveOrganizationActorContextMiddleware = new ResolveOrganizationActorContextMiddleware();
        $capturingHandler                          = new CapturingHandler();

        $resolveOrganizationActorContextMiddleware->process($serverRequest, $capturingHandler);

        $resolvedRequest = $capturingHandler->lastRequest;
        self::assertNotNull($resolvedRequest);
        $actorContext = $resolvedRequest->getAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT);
        self::assertNotNull($actorContext);
        self::assertSame('org-1', $actorContext->organizationId);
        self::assertSame('user-1', $actorContext->actorId);
        self::assertSame('owner', $actorContext->actorRole);
    }
}

final class CapturingHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $lastRequest = null;

    public function handle(ServerRequestInterface $request): EmptyResponse
    {
        $this->lastRequest = $request;

        return new EmptyResponse();
    }
}
