<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Session\SessionInterface;
use Packio\Application\Middleware\CsrfValidationMiddleware;
use Packio\Application\Routing\CsrfRouteOptions;
use Packio\Application\Security\Csrf\CsrfTokenContract;
use Packio\Application\Security\Csrf\CsrfTokenManager;
use Packio\Application\Security\Http\TrustedProxyMatcher;
use Packio\Application\Security\Http\TrustedProxyRequestOriginResolver;
use Packio\Application\Security\RateLimiter\RateLimiterInterface;
use PackioTest\Support\Fake\InMemoryRateLimiter;
use PackioTest\Support\Fake\InMemorySession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;
use Stringable;

use function array_keys;
use function json_encode;
use function str_repeat;

#[CoversClass(CsrfValidationMiddleware::class)]
final class CsrfValidationMiddlewareTest extends TestCase
{
    #[Test]
    public function safeMethodsPassWithoutRouteOrSession(): void
    {
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $handler  = new CsrfCapturingHandler();
            $response = $this->middleware()->process(new ServerRequest([], [], '/profile', $method), $handler);

            self::assertTrue($handler->called);
            self::assertSame(204, $response->getStatusCode());
        }
    }

    #[Test]
    public function traceIsNotTreatedAsASafeMethod(): void
    {
        $csrfCapturingHandler  = new CsrfCapturingHandler();
        $response              = $this->middleware()->process($this->matchedRequest('TRACE'), $csrfCapturingHandler);

        self::assertFalse($csrfCapturingHandler->called);
        self::assertSame(419, $response->getStatusCode());
    }

    #[Test]
    public function unmatchedRouteIsSkippedInsteadOfFailingWith419(): void
    {
        $csrfSpyLogger              = new CsrfSpyLogger();
        $csrfCapturingHandler       = new CsrfCapturingHandler();
        $serverRequest              = new ServerRequest([], [], 'https://packio.test/organization/acme/jobs', 'POST')
            ->withAttribute(RouteResult::class, RouteResult::fromRouteFailure(null))
        ;

        $response = $this->middleware(csrfSpyLogger: $csrfSpyLogger)->process($serverRequest, $csrfCapturingHandler);

        self::assertTrue($csrfCapturingHandler->called);
        self::assertSame(204, $response->getStatusCode());
        self::assertCount(0, $csrfSpyLogger->records);
    }

    #[Test]
    public function missingRouteMetadataRemainsFailClosed(): void
    {
        $csrfCapturingHandler       = new CsrfCapturingHandler();
        $serverRequest              = new ServerRequest([], [], 'https://packio.test/anything', 'POST');

        $response = $this->middleware()->process($serverRequest, $csrfCapturingHandler);

        self::assertFalse($csrfCapturingHandler->called);
        self::assertSame(419, $response->getStatusCode());
    }

    #[Test]
    public function explicitlyExemptRoutePassesWithoutBrowserHeadersOrSession(): void
    {
        $csrfCapturingHandler  = new CsrfCapturingHandler();
        $response              = $this->middleware()->process($this->matchedRequest('POST', [
            CsrfRouteOptions::EXEMPT => true,
        ]), $csrfCapturingHandler);

        self::assertTrue($csrfCapturingHandler->called);
        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function unsafeApiPathWithoutExplicitPolicyDoesNotBypassValidation(): void
    {
        $csrfCapturingHandler  = new CsrfCapturingHandler();
        $response              = $this->middleware()->process($this->matchedRequest('POST', [], '/api/v1/resources'), $csrfCapturingHandler);

        self::assertFalse($csrfCapturingHandler->called);
        self::assertSame(419, $response->getStatusCode());
    }

    #[Test]
    #[DataProvider('unsafeMethods')]
    public function validTokenAndExactOriginAllowAnUnsafeRequest(string $method): void
    {
        $inMemorySession      = new InMemorySession();
        $token                = $this->manager()->getOrCreate($inMemorySession);
        $csrfCapturingHandler = new CsrfCapturingHandler();

        $response = $this->middleware()->process(
            $this->matchedRequest($method)
                ->withAttribute(SessionInterface::class, $inMemorySession)
                ->withHeader('Origin', 'https://packio.test')
                ->withHeader(CsrfTokenContract::HEADER, $token),
            $csrfCapturingHandler,
        );

        self::assertTrue($csrfCapturingHandler->called);
        self::assertSame(204, $response->getStatusCode());
    }

    /** @return array<string, array{string}> */
    public static function unsafeMethods(): array
    {
        return [
            'POST'   => ['POST'],
            'PUT'    => ['PUT'],
            'PATCH'  => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }

    #[Test]
    public function refererIsUsedOnlyWhenOriginIsAbsent(): void
    {
        $inMemorySession      = new InMemorySession();
        $token                = $this->manager()->getOrCreate($inMemorySession);
        $csrfCapturingHandler = new CsrfCapturingHandler();

        $response = $this->middleware()->process(
            $this->matchedRequest('POST')
                ->withAttribute(SessionInterface::class, $inMemorySession)
                ->withHeader('Referer', 'https://packio.test/organizations/acme?tab=sources')
                ->withHeader(CsrfTokenContract::HEADER, $token),
            $csrfCapturingHandler,
        );

        self::assertTrue($csrfCapturingHandler->called);
        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function originWithUserinfoReturns419(): void
    {
        $inMemorySession      = new InMemorySession();
        $token                = $this->manager()->getOrCreate($inMemorySession);
        $csrfCapturingHandler = new CsrfCapturingHandler();

        foreach (['https://user@packio.test', 'https://user:pass@packio.test', 'https://:pass@packio.test'] as $origin) {
            $response = $this->middleware()->process(
                $this->matchedRequest('POST')
                    ->withAttribute(SessionInterface::class, $inMemorySession)
                    ->withHeader('Origin', $origin)
                    ->withHeader(CsrfTokenContract::HEADER, $token),
                $csrfCapturingHandler,
            );

            self::assertFalse($csrfCapturingHandler->called, $origin);
            self::assertSame(419, $response->getStatusCode(), $origin);
        }
    }

    #[Test]
    public function refererWithQueryAndFragmentIsAccepted(): void
    {
        $inMemorySession      = new InMemorySession();
        $token                = $this->manager()->getOrCreate($inMemorySession);
        $csrfCapturingHandler = new CsrfCapturingHandler();

        $response = $this->middleware()->process(
            $this->matchedRequest('POST')
                ->withAttribute(SessionInterface::class, $inMemorySession)
                ->withHeader('Referer', 'https://packio.test/x?page=2#a')
                ->withHeader(CsrfTokenContract::HEADER, $token),
            $csrfCapturingHandler,
        );

        self::assertTrue($csrfCapturingHandler->called);
        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function trustedProxyForwardedOriginIsUsedForExactOriginValidation(): void
    {
        $inMemorySession      = new InMemorySession();
        $token                = $this->manager()->getOrCreate($inMemorySession);
        $csrfCapturingHandler = new CsrfCapturingHandler();
        $serverRequest        = $this->matchedRequest(
            'POST',
            [],
            '/settings',
            [
                'REMOTE_ADDR' => '10.0.0.1',
            ],
            'http://internal.test:8080',
        )
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withHeader('Origin', 'https://packio.test')
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withHeader('X-Forwarded-Host', 'packio.test')
            ->withHeader(CsrfTokenContract::HEADER, $token)
        ;

        $response = $this->middleware(trustedProxies: ['10.0.0.1'])->process($serverRequest, $csrfCapturingHandler);

        self::assertTrue($csrfCapturingHandler->called);
        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function untrustedProxyHeadersCannotChangeTheRequestOrigin(): void
    {
        $inMemorySession      = new InMemorySession();
        $token                = $this->manager()->getOrCreate($inMemorySession);
        $csrfCapturingHandler = new CsrfCapturingHandler();
        $serverRequest        = $this->matchedRequest(
            'POST',
            [],
            '/settings',
            [
                'REMOTE_ADDR' => '198.51.100.7',
            ],
            'http://internal.test:8080',
        )
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withHeader('Origin', 'https://packio.test')
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withHeader('X-Forwarded-Host', 'packio.test')
            ->withHeader(CsrfTokenContract::HEADER, $token)
        ;

        $response = $this->middleware(trustedProxies: ['10.0.0.1'])->process($serverRequest, $csrfCapturingHandler);

        self::assertFalse($csrfCapturingHandler->called);
        self::assertSame(419, $response->getStatusCode());
    }

    #[Test]
    public function missingOrMismatchedSourceOriginReturns419(): void
    {
        $inMemorySession = new InMemorySession();
        $token           = $this->manager()->getOrCreate($inMemorySession);

        foreach ([null, 'https://evil.test', 'https://subdomain.packio.test', 'https://packio.test:8443'] as $origin) {
            $request = $this->matchedRequest('POST')
                ->withAttribute(SessionInterface::class, $inMemorySession)
                ->withHeader(CsrfTokenContract::HEADER, $token)
            ;
            if (null !== $origin) {
                $request = $request->withHeader('Origin', $origin);
            }

            $handler  = new CsrfCapturingHandler();
            $response = $this->middleware()->process($request, $handler);

            self::assertFalse($handler->called);
            self::assertSame(419, $response->getStatusCode());
        }
    }

    #[Test]
    public function missingMalformedAndMismatchedTokensReturn419WithoutCallingTheHandler(): void
    {
        $inMemorySession = new InMemorySession();
        $token           = $this->manager()->getOrCreate($inMemorySession);

        foreach ([null, 'short-token', str_repeat('a', 64)] as $submittedToken) {
            $request = $this->matchedRequest('POST')
                ->withAttribute(SessionInterface::class, $inMemorySession)
                ->withHeader('Origin', 'https://packio.test')
            ;
            if (null !== $submittedToken) {
                $request = $request->withHeader(CsrfTokenContract::HEADER, $submittedToken);
            }

            $handler  = new CsrfCapturingHandler();
            $response = $this->middleware()->process($request, $handler);

            self::assertFalse($handler->called);
            self::assertSame(419, $response->getStatusCode());
        }

        self::assertNotSame(str_repeat('a', 64), $token);
    }

    #[Test]
    public function bodyOnlyTokenAndMultipleTokenHeadersAreRejected(): void
    {
        $inMemorySession = new InMemorySession();
        $token           = $this->manager()->getOrCreate($inMemorySession);

        $serverRequest = $this->matchedRequest('POST')
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withHeader('Origin', 'https://packio.test')
            ->withParsedBody([
                'csrf_token' => $token,
            ])
        ;
        $multiHeader = $this->matchedRequest('POST')
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withHeader('Origin', 'https://packio.test')
            ->withHeader(CsrfTokenContract::HEADER, [$token, $token])
        ;

        foreach ([$serverRequest, $multiHeader] as $request) {
            $handler  = new CsrfCapturingHandler();
            $response = $this->middleware()->process($request, $handler);

            self::assertFalse($handler->called);
            self::assertSame(419, $response->getStatusCode());
        }
    }

    #[Test]
    public function expectedValidationFailuresAreLoggedWithoutSecrets(): void
    {
        $csrfSpyLogger   = new CsrfSpyLogger();
        $inMemorySession = new InMemorySession();
        $stored          = $this->manager()->getOrCreate($inMemorySession);
        $submitted       = str_repeat('b', 64);
        $middleware      = $this->middleware(csrfSpyLogger: $csrfSpyLogger);
        $requests        = [
            'missing_origin'  => $this->matchedRequest('POST')
                ->withAttribute(SessionInterface::class, $inMemorySession)
                ->withHeader(CsrfTokenContract::HEADER, $stored),
            'origin_mismatch' => $this->matchedRequest('POST')
                ->withAttribute(SessionInterface::class, $inMemorySession)
                ->withHeader('Origin', 'https://attacker.example')
                ->withHeader(CsrfTokenContract::HEADER, $stored),
            'missing_session' => $this->matchedRequest('POST')
                ->withHeader('Origin', 'https://packio.test')
                ->withHeader(CsrfTokenContract::HEADER, $stored),
            'missing_token'   => $this->matchedRequest('POST')
                ->withAttribute(SessionInterface::class, $inMemorySession)
                ->withHeader('Origin', 'https://packio.test'),
            'invalid_token'   => $this->matchedRequest('POST')
                ->withAttribute(SessionInterface::class, $inMemorySession)
                ->withHeader('Origin', 'https://packio.test')
                ->withHeader(CsrfTokenContract::HEADER, $submitted),
        ];

        foreach ($requests as $request) {
            $handler  = new CsrfCapturingHandler();
            $response = $middleware->process($request, $handler);

            self::assertSame(419, $response->getStatusCode());
            self::assertFalse($handler->called);
        }

        self::assertCount(5, $csrfSpyLogger->records);
        foreach ($csrfSpyLogger->records as $record) {
            self::assertSame('notice', $record['level']);
            self::assertSame('CSRF validation failed', $record['message']);
            self::assertContains($record['context']['reason'], array_keys($requests));
            self::assertSame('test.route', $record['context']['route']);
            self::assertSame('POST', $record['context']['method']);
            self::assertSame('/settings', $record['context']['path']);
            self::assertNull($record['context']['request_id']);
            self::assertArrayNotHasKey('token', $record['context']);
            self::assertArrayNotHasKey('headers', $record['context']);
            self::assertArrayNotHasKey('body', $record['context']);
            self::assertArrayNotHasKey('cookie', $record['context']);
            self::assertArrayNotHasKey('authorization', $record['context']);
            self::assertStringNotContainsString($stored, (string) json_encode($record));
            self::assertStringNotContainsString($submitted, (string) json_encode($record));
        }
    }

    #[Test]
    public function expectedValidationFailureDoesNotLogTheRouteOrPathParameters(): void
    {
        $csrfSpyLogger   = new CsrfSpyLogger();
        $inMemorySession = new InMemorySession();
        $token           = $this->manager()->getOrCreate($inMemorySession);
        $secret          = 'invitation-secret';
        $route           = new Route('/organization/invite/:token/accept', new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        }, ['POST'], 'organization.invite.accept.submit');
        $serverRequest = new ServerRequest([], [], 'https://packio.test/organization/invite/' . $secret . '/accept', 'POST')
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(RouteResult::class, RouteResult::fromRoute($route, [
                'token' => $secret,
            ]))
            ->withHeader('Origin', 'https://packio.test')
            ->withHeader(CsrfTokenContract::HEADER, str_repeat('c', 64))
        ;

        $this->middleware(csrfSpyLogger: $csrfSpyLogger)->process($serverRequest, new CsrfCapturingHandler());

        self::assertCount(1, $csrfSpyLogger->records);
        self::assertSame('/organization/invite/:token/accept', $csrfSpyLogger->records[0]['context']['path']);
        self::assertStringNotContainsString($secret, (string) json_encode($csrfSpyLogger->records[0]));
        self::assertTrue($this->manager()->validate($inMemorySession, $token));
    }

    #[Test]
    public function expectedValidationFailuresAreRateLimited(): void
    {
        $csrfSpyLogger = new CsrfSpyLogger();
        $middleware    = $this->middleware($csrfSpyLogger);
        $serverRequest = $this->matchedRequest('POST');

        $middleware->process($serverRequest, new CsrfCapturingHandler());
        $middleware->process($serverRequest, new CsrfCapturingHandler());

        self::assertCount(1, $csrfSpyLogger->records);
        self::assertSame('notice', $csrfSpyLogger->records[0]['level']);
        self::assertSame('missing_origin', $csrfSpyLogger->records[0]['context']['reason']);
    }

    #[Test]
    public function invalidRoutePolicyLogsOneRateLimitedError(): void
    {
        $csrfSpyLogger = new CsrfSpyLogger();
        $middleware    = $this->middleware($csrfSpyLogger);
        $serverRequest = $this->matchedRequest('POST', [
            CsrfRouteOptions::EXEMPT => 'yes',
        ]);

        $firstResponse  = $middleware->process($serverRequest, new CsrfCapturingHandler());
        $secondResponse = $middleware->process($serverRequest, new CsrfCapturingHandler());

        self::assertSame(419, $firstResponse->getStatusCode());
        self::assertSame(419, $secondResponse->getStatusCode());
        self::assertCount(1, $csrfSpyLogger->records);
        self::assertSame('error', $csrfSpyLogger->records[0]['level']);
        self::assertSame('invalid_route_policy', $csrfSpyLogger->records[0]['context']['reason']);
    }

    /**
     * @param array<string, mixed> $options
     * @param non-empty-string     $path
     * @param array<string, mixed> $serverParams
     * @param non-empty-string     $baseOrigin
     */
    private function matchedRequest(
        string $method,
        array $options = [],
        string $path = '/settings',
        array $serverParams = [],
        string $baseOrigin = 'https://packio.test',
    ): ServerRequestInterface {
        $route = new Route($path, new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        }, [$method], 'test.route');
        $route->setOptions($options);

        return new ServerRequest($serverParams, [], $baseOrigin . $path, $method)
            ->withAttribute(RouteResult::class, RouteResult::fromRoute($route))
        ;
    }

    private function manager(): CsrfTokenManager
    {
        return new CsrfTokenManager();
    }

    /** @param list<string> $trustedProxies */
    private function middleware(
        ?CsrfSpyLogger $csrfSpyLogger = null,
        array $trustedProxies = [],
        ?RateLimiterInterface $rateLimiter = null,
    ): CsrfValidationMiddleware {
        return new CsrfValidationMiddleware(
            $this->manager(),
            $rateLimiter ?? new InMemoryRateLimiter(),
            $csrfSpyLogger,
            new TrustedProxyRequestOriginResolver(new TrustedProxyMatcher([
                'trusted_proxies' => $trustedProxies,
            ])),
        );
    }
}

final class CsrfCapturingHandler implements RequestHandlerInterface
{
    public bool $called = false;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->called = true;

        return new EmptyResponse(204);
    }
}

final class CsrfSpyLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string|Stringable, context: array<mixed>}> */
    public array $records = [];

    /** @param array<mixed> $context */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
