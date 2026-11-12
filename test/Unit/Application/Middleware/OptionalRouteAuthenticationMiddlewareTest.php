<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use InvalidArgumentException;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Packio\Application\Middleware\OptionalRouteAuthenticationMiddleware;
use Packio\Application\Routing\AuthenticationRouteOptions;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationContext;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Exception\UnknownAuthenticationProfileException;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use UnexpectedValueException;

#[CoversClass(OptionalRouteAuthenticationMiddleware::class)]
#[AllowMockObjectsWithoutExpectations]
final class OptionalRouteAuthenticationMiddlewareTest extends TestCase
{
    #[Test]
    public function authenticatesMatchedRoutesWithoutAnExplicitProfileThroughTheDefaultProfile(): void
    {
        $defaultProfile = $this->defaultProfile($this->createAuthenticator(expectsAuthentication: true));
        $provider       = $this->createMock(AuthenticationProfileProviderInterface::class);
        $provider->expects(self::once())->method('getDefaultProfile')->willReturn($defaultProfile);
        $provider->expects(self::never())->method('get');

        $optionalRouteAuthenticationCapturingHandler = new OptionalRouteAuthenticationCapturingHandler();
        new OptionalRouteAuthenticationMiddleware($provider)->process($this->matchedRequest([]), $optionalRouteAuthenticationCapturingHandler);

        self::assertTrue($optionalRouteAuthenticationCapturingHandler->called);
    }

    #[Test]
    public function skipsTheDefaultProfileForAnApiProfileRoute(): void
    {
        $defaultProfile = $this->defaultProfile($this->createAuthenticator(expectsAuthentication: false));
        $apiProfile     = $this->createMock(AuthenticationProfileInterface::class);
        $apiProfile->method('name')->willReturn(AuthenticationRouteOptions::API_PROFILE);
        $provider       = $this->createMock(AuthenticationProfileProviderInterface::class);
        $provider->expects(self::once())->method('getDefaultProfile')->willReturn($defaultProfile);
        $provider->expects(self::once())->method('get')->with(AuthenticationRouteOptions::API_PROFILE)->willReturn($apiProfile);

        $optionalRouteAuthenticationCapturingHandler = new OptionalRouteAuthenticationCapturingHandler();
        new OptionalRouteAuthenticationMiddleware($provider)->process($this->matchedRequest([
            AuthenticationRouteOptions::PROFILE => AuthenticationRouteOptions::API_PROFILE,
        ]), $optionalRouteAuthenticationCapturingHandler);

        self::assertTrue($optionalRouteAuthenticationCapturingHandler->called);
    }

    #[Test]
    public function authenticatesUnmatchedRequestsThroughTheDefaultProfile(): void
    {
        $defaultProfile = $this->defaultProfile($this->createAuthenticator(expectsAuthentication: true));
        $provider       = $this->createMock(AuthenticationProfileProviderInterface::class);
        $provider->expects(self::once())->method('getDefaultProfile')->willReturn($defaultProfile);
        $provider->expects(self::never())->method('get');

        $optionalRouteAuthenticationCapturingHandler = new OptionalRouteAuthenticationCapturingHandler();
        new OptionalRouteAuthenticationMiddleware($provider)->process(new ServerRequest(), $optionalRouteAuthenticationCapturingHandler);

        self::assertTrue($optionalRouteAuthenticationCapturingHandler->called);
    }

    #[Test]
    public function authenticatesPublicApiRoutesThroughTheDefaultProfile(): void
    {
        $defaultProfile = $this->defaultProfile($this->createAuthenticator(expectsAuthentication: true));
        $provider       = $this->createMock(AuthenticationProfileProviderInterface::class);
        $provider->expects(self::once())->method('getDefaultProfile')->willReturn($defaultProfile);
        $provider->expects(self::never())->method('get');

        $optionalRouteAuthenticationCapturingHandler = new OptionalRouteAuthenticationCapturingHandler();
        new OptionalRouteAuthenticationMiddleware($provider)->process($this->matchedRequest([], '/api/v1/health'), $optionalRouteAuthenticationCapturingHandler);

        self::assertTrue($optionalRouteAuthenticationCapturingHandler->called);
    }

    #[Test]
    public function failsClosedForAnUnknownExplicitProfile(): void
    {
        $defaultProfile = $this->defaultProfile($this->createAuthenticator(expectsAuthentication: false));
        $provider       = $this->createMock(AuthenticationProfileProviderInterface::class);
        $provider->expects(self::once())->method('getDefaultProfile')->willReturn($defaultProfile);
        $provider->expects(self::once())
            ->method('get')
            ->with('unknown')
            ->willThrowException(UnknownAuthenticationProfileException::forName('unknown', ['web', 'api']))
        ;

        $this->expectException(UnknownAuthenticationProfileException::class);

        new OptionalRouteAuthenticationMiddleware($provider)->process($this->matchedRequest([
            AuthenticationRouteOptions::PROFILE => 'unknown',
        ]), new OptionalRouteAuthenticationCapturingHandler());
    }

    #[Test]
    public function rejectsMalformedExplicitProfileConfiguration(): void
    {
        $defaultProfile = $this->defaultProfile($this->createAuthenticator(expectsAuthentication: false));
        $provider       = $this->createMock(AuthenticationProfileProviderInterface::class);
        $provider->expects(self::once())->method('getDefaultProfile')->willReturn($defaultProfile);
        $provider->expects(self::never())->method('get');

        $this->expectException(UnexpectedValueException::class);

        new OptionalRouteAuthenticationMiddleware($provider)->process($this->matchedRequest([
            AuthenticationRouteOptions::PROFILE => [],
        ]), new OptionalRouteAuthenticationCapturingHandler());
    }

    private function defaultProfile(AuthenticatorInterface $authenticator): AuthenticationProfileInterface
    {
        $profile = $this->createMock(AuthenticationProfileInterface::class);
        $profile->method('name')->willReturn('web');
        $profile->method('optionalAuthenticateMiddleware')->willReturn(new OptionalAuthenticateMiddleware(
            $authenticator,
            $this->createMock(TokenStorageProviderInterface::class),
            new class implements TokenTransportInterface {
                public function fetch(ServerRequestInterface $serverRequest): ?string
                {
                    return null;
                }

                public function attach(
                    ResponseInterface $response,
                    TokenInterface $token
                ): ResponseInterface {
                    return $response;
                }

                public function detach(ResponseInterface $response): ResponseInterface
                {
                    return $response;
                }
            },
            'session',
        ));

        return $profile;
    }

    private function createAuthenticator(bool $expectsAuthentication): AuthenticatorInterface
    {
        /** @var AuthenticatorInterface&MockObject $authenticator */
        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $authenticator->expects($expectsAuthentication ? self::once() : self::never())
            ->method('authenticate')
            ->with(null)
            ->willReturn(new AuthenticationContext())
        ;

        return $authenticator;
    }

    /** @param array<string, mixed> $options */
    private function matchedRequest(array $options, string $path = '/test'): ServerRequestInterface
    {
        if ('' === $path) {
            throw new InvalidArgumentException('Test route path must not be empty.');
        }

        $route = new Route($path, new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        });
        $route->setOptions($options);

        return new ServerRequest()->withAttribute(RouteResult::class, RouteResult::fromRoute($route));
    }
}

final class OptionalRouteAuthenticationCapturingHandler implements RequestHandlerInterface
{
    public bool $called = false;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->called = true;

        return new EmptyResponse();
    }
}
