<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Packio\Application\Middleware\ApiAuthenticationAttemptRateLimitMiddleware;
use Packio\Application\Routing\AuthenticationRouteOptions;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\RateLimiter\ApiAuthenticationAttemptLimiter;
use Packio\Application\Security\RateLimiter\RateLimiterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;

#[CoversClass(ApiAuthenticationAttemptRateLimitMiddleware::class)]
final class ApiAuthenticationAttemptRateLimitMiddlewareTest extends TestCase
{
    #[Test]
    public function blocksCredentialBearingApiRequestsAfterPriorFailuresBeforeTheAuthenticationProfileRuns(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::once())
            ->method('isExceeded')
            ->with('api_authentication:ip:203.0.113.10', 60, 60)
            ->willReturn(true)
        ;
        $apiAuthenticationAttemptRateLimitCapturingHandler = new ApiAuthenticationAttemptRateLimitCapturingHandler();

        $response = new ApiAuthenticationAttemptRateLimitMiddleware(
            $this->profileProvider('api-token'),
            $this->attemptLimiter($rateLimiter),
        )->process($this->apiRequest(), $apiAuthenticationAttemptRateLimitCapturingHandler);

        self::assertFalse($apiAuthenticationAttemptRateLimitCapturingHandler->called);
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('60', $response->getHeaderLine('Retry-After'));
        self::assertStringNotContainsString('Authorization', (string) $response->getBody());
        self::assertStringNotContainsString('api-token', (string) $response->getBody());
    }

    #[Test]
    public function doesNotCheckOrConsumeTheFailureQuotaForCredentialFreeApiRequests(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::never())->method('isExceeded');
        $rateLimiter->expects(self::never())->method('attempt');
        $apiAuthenticationAttemptRateLimitCapturingHandler = new ApiAuthenticationAttemptRateLimitCapturingHandler();

        $response = new ApiAuthenticationAttemptRateLimitMiddleware(
            $this->profileProvider(null),
            $this->attemptLimiter($rateLimiter),
        )->process($this->apiRequest(), $apiAuthenticationAttemptRateLimitCapturingHandler);

        self::assertTrue($apiAuthenticationAttemptRateLimitCapturingHandler->called);
        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function doesNotRateLimitRoutesWithoutTheApiAuthenticationProfile(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::never())->method('isExceeded');
        $rateLimiter->expects(self::never())->method('attempt');
        $apiAuthenticationAttemptRateLimitCapturingHandler = new ApiAuthenticationAttemptRateLimitCapturingHandler();

        $response = new ApiAuthenticationAttemptRateLimitMiddleware(
            $this->createStub(AuthenticationProfileProviderInterface::class),
            $this->attemptLimiter($rateLimiter),
        )->process(new ServerRequest(), $apiAuthenticationAttemptRateLimitCapturingHandler);

        self::assertTrue($apiAuthenticationAttemptRateLimitCapturingHandler->called);
        self::assertSame(204, $response->getStatusCode());
    }

    /** @return array<string, array{max_attempts: int, decay_seconds: int}> */
    private function rateLimits(): array
    {
        return [
            'api_authentication' => [
                'max_attempts'  => 60,
                'decay_seconds' => 60,
            ],
        ];
    }

    private function attemptLimiter(RateLimiterInterface $rateLimiter): ApiAuthenticationAttemptLimiter
    {
        return new ApiAuthenticationAttemptLimiter(
            $rateLimiter,
            $this->clientIpResolver('203.0.113.10'),
            $this->rateLimits(),
        );
    }

    private function clientIpResolver(string $ip): ClientIpResolverInterface
    {
        $clientIpResolver = $this->createStub(ClientIpResolverInterface::class);
        $clientIpResolver->method('resolve')->willReturn($ip);

        return $clientIpResolver;
    }

    private function profileProvider(?string $credential): AuthenticationProfileProviderInterface
    {
        $transport = $this->createMock(TokenTransportInterface::class);
        $transport->expects(self::once())->method('fetch')->willReturn($credential);
        $profile = $this->createMock(AuthenticationProfileInterface::class);
        $profile->expects(self::once())->method('transport')->willReturn($transport);
        $provider = $this->createMock(AuthenticationProfileProviderInterface::class);
        $provider->expects(self::once())->method('get')->with(AuthenticationRouteOptions::API_PROFILE)->willReturn($profile);

        return $provider;
    }

    private function apiRequest(): ServerRequest
    {
        $route = new Route('/api/v1/me', new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): EmptyResponse
            {
                return new EmptyResponse();
            }
        }, ['GET'], 'api.v1.me');

        $route->setOptions([
            AuthenticationRouteOptions::PROFILE => AuthenticationRouteOptions::API_PROFILE,
        ]);

        return new ServerRequest()->withAttribute(RouteResult::class, RouteResult::fromRoute($route));
    }
}

final class ApiAuthenticationAttemptRateLimitCapturingHandler implements RequestHandlerInterface
{
    public bool $called = false;

    public function handle(ServerRequestInterface $request): EmptyResponse
    {
        $this->called = true;

        return new EmptyResponse(204);
    }
}
