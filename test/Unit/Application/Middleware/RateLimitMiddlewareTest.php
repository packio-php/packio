<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Flash\FlashMessages;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Session\Session;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RateLimitMiddleware;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\RateLimiter\RateLimiterInterface;
use Packio\Identity\Infrastructure\Handler\LoginHandler;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Model\Page;
use Sirix\InertiaPsr15\Service\InertiaFactory;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\InertiaPsr15\View\RootViewProviderInterface;
use Sirix\InertiaPsr15MezzioFlash\MezzioFlashProvider;

use function json_decode;

#[CoversClass(RateLimitMiddleware::class)]
#[AllowMockObjectsWithoutExpectations]
final class RateLimitMiddlewareTest extends TestCase
{
    private const array RATE_LIMIT_CONFIG = [
        'storage_dir'      => '/tmp/rate-limits',
        'login'            => [
            'max_attempts'  => 5,
            'decay_seconds' => 900,
        ],
        'test_json_action' => [
            'max_attempts'  => 30,
            'decay_seconds' => 60,
        ],
    ];

    #[Test]
    public function passesRequestWhenNoRateLimitRule(): void
    {
        $rateLimitMiddleware = $this->createMiddleware(
            $this->createRateLimiter(expectsAttempt: false),
            $this->createClientIpResolver('127.0.0.1'),
            self::RATE_LIMIT_CONFIG,
        );

        $serverRequest             = $this->requestWithRouteOptions([]);
        $rateLimitCapturingHandler = new RateLimitCapturingHandler();
        $rateLimitMiddleware->process($serverRequest, $rateLimitCapturingHandler);

        self::assertTrue($rateLimitCapturingHandler->called);
    }

    #[Test]
    public function passesRequestWhenRateLimitNotExceeded(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::once())
            ->method('attempt')
            ->with(
                self::stringContains('test_json_action:ip:127.0.0.1'),
                30,
                60,
            )
            ->willReturn(true)
        ;

        $rateLimitMiddleware = $this->createMiddleware(
            $rateLimiter,
            $this->createClientIpResolver('127.0.0.1'),
            self::RATE_LIMIT_CONFIG,
        );

        $serverRequest = $this->requestWithRouteOptions([
            'rate_limit' => [[
                'key'      => 'test_json_action',
                'by'       => ['ip'],
                'response' => 'json',
                'message'  => 'Too many requests.',
            ]],
        ]);

        $rateLimitCapturingHandler = new RateLimitCapturingHandler();
        $rateLimitMiddleware->process($serverRequest, $rateLimitCapturingHandler);

        self::assertTrue($rateLimitCapturingHandler->called);
    }

    #[Test]
    public function returnsJsonResponseWhenRateLimitExceeded(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::once())
            ->method('attempt')
            ->willReturn(false)
        ;

        $rateLimitMiddleware = $this->createMiddleware(
            $rateLimiter,
            $this->createClientIpResolver('127.0.0.1'),
            self::RATE_LIMIT_CONFIG,
        );

        $serverRequest = $this->requestWithRouteOptions([
            'rate_limit' => [[
                'key'      => 'test_json_action',
                'by'       => ['ip'],
                'response' => 'json',
                'message'  => 'Too many requests. Please try again later.',
            ]],
        ]);

        $rateLimitCapturingHandler = new RateLimitCapturingHandler();
        $response                  = $rateLimitMiddleware->process($serverRequest, $rateLimitCapturingHandler);

        self::assertFalse($rateLimitCapturingHandler->called);
        self::assertSame(429, $response->getStatusCode());
        self::assertStringContainsString('Too many requests', (string) $response->getBody());
        self::assertStringContainsString('429', (string) $response->getBody());
        self::assertTrue($response->hasHeader('Retry-After'));
        self::assertSame('60', $response->getHeaderLine('Retry-After'));
    }

    #[Test]
    public function actualLoginPolicyStopsAuthenticationWithRedirectAndFlash(): void
    {
        $rateLimit      = new ReflectionClass(LoginHandler::class)->getAttributes(RateLimit::class)[0]->newInstance();
        $rateLimiter    = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::once())
            ->method('attempt')
            ->with('login:ip:127.0.0.1', 5, 900)
            ->willReturn(false)
        ;

        $rateLimitCapturingHandler  = new RateLimitCapturingHandler();
        $serverRequest              = $this->requestWithRouteOptions($rateLimit->getDefaults());
        $inertia                    = $serverRequest->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        $response                   = $this->createMiddleware(
            $rateLimiter,
            $this->createClientIpResolver('127.0.0.1'),
            self::RATE_LIMIT_CONFIG,
        )->process($serverRequest, $rateLimitCapturingHandler);

        self::assertFalse($rateLimitCapturingHandler->called);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('900', $response->getHeaderLine('Retry-After'));
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
        self::assertInstanceOf(TestInertia::class, $inertia);
        self::assertSame([
            'error' => 'Too many login attempts. Please try again later.',
        ], $inertia->pendingFlash());
    }

    #[Test]
    public function loginThrottleFlashReachesTheNextInertiaPageOnce(): void
    {
        $rateLimit              = new ReflectionClass(LoginHandler::class)->getAttributes(RateLimit::class)[0]->newInstance();
        $rateLimitMiddleware    = $this->createMiddleware(
            $this->createRateLimiter(expectsAttempt: true, returns: false),
            $this->createClientIpResolver('127.0.0.1'),
            self::RATE_LIMIT_CONFIG,
        );
        $inertiaMiddleware = new InertiaMiddleware(
            new InertiaFactory(new ResponseFactory(), new StreamFactory(), new class implements RootViewProviderInterface {
                public function __invoke(Page $page): string
                {
                    return '<html></html>';
                }
            }),
            flashProvider: new MezzioFlashProvider(),
        );
        $session       = new Session([]);
        $serverRequest = $this->requestWithRouteOptions($rateLimit->getDefaults())
            ->withHeader('X-Inertia', 'true')
            ->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, FlashMessages::createFromSession($session))
        ;
        $rateLimitCapturingHandler = new RateLimitCapturingHandler();
        $response                  = $inertiaMiddleware->process($serverRequest, new readonly class($rateLimitMiddleware, $rateLimitCapturingHandler) implements RequestHandlerInterface {
            public function __construct(
                private RateLimitMiddleware $rateLimitMiddleware,
                private RequestHandlerInterface $requestHandler,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->rateLimitMiddleware->process($request, $this->requestHandler);
            }
        });

        self::assertFalse($rateLimitCapturingHandler->called);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
        self::assertSame('900', $response->getHeaderLine('Retry-After'));

        $pageHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
                TestCase::assertInstanceOf(InertiaInterface::class, $inertia);

                return $inertia->render('Auth/Login');
            }
        };
        foreach ([[
            'error' => 'Too many login attempts. Please try again later.',
        ], []] as $expectedFlash) {
            $nextRequest = new ServerRequest([], [], '/auth/login', 'GET')
                ->withHeader('X-Inertia', 'true')
                ->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, FlashMessages::createFromSession($session))
            ;
            $pageResponse = $inertiaMiddleware->process($nextRequest, $pageHandler);
            $page         = json_decode((string) $pageResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame(200, $pageResponse->getStatusCode());
            self::assertSame('Auth/Login', $page['component']);
            self::assertSame($expectedFlash, $page['flash'] ?? []);
            self::assertArrayNotHasKey('flash', $page['props']);
        }
    }

    #[Test]
    public function returnsRedirectResponseWhenConfigured(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::once())
            ->method('attempt')
            ->willReturn(false)
        ;

        $inertiaNotificationMessenger = new InertiaNotificationMessenger();
        $rateLimitMiddleware          = new RateLimitMiddleware(
            $rateLimiter,
            $this->createClientIpResolver('127.0.0.1'),
            $inertiaNotificationMessenger,
            self::RATE_LIMIT_CONFIG,
        );

        $serverRequest = $this->requestWithRouteOptions([
            'rate_limit' => [[
                'key'         => 'test_json_action',
                'by'          => ['ip'],
                'response'    => 'redirect',
                'redirect_to' => '/auth/login',
                'message'     => 'Too many attempts. Please try again.',
                'notify'      => 'flash',
            ]],
        ]);

        $rateLimitCapturingHandler = new RateLimitCapturingHandler();
        $response                  = $rateLimitMiddleware->process($serverRequest, $rateLimitCapturingHandler);

        self::assertFalse($rateLimitCapturingHandler->called);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function returnsEmptyResponseWhenConfigured(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::once())
            ->method('attempt')
            ->willReturn(false)
        ;

        $rateLimitMiddleware = $this->createMiddleware(
            $rateLimiter,
            $this->createClientIpResolver('127.0.0.1'),
            self::RATE_LIMIT_CONFIG,
        );

        $serverRequest = $this->requestWithRouteOptions([
            'rate_limit' => [[
                'key'      => 'test_json_action',
                'by'       => ['ip'],
                'response' => 'empty',
            ]],
        ]);

        $rateLimitCapturingHandler = new RateLimitCapturingHandler();
        $response                  = $rateLimitMiddleware->process($serverRequest, $rateLimitCapturingHandler);

        self::assertFalse($rateLimitCapturingHandler->called);
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertTrue($response->hasHeader('Retry-After'));
    }

    #[Test]
    public function respectsHttpMethodFiltering(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::never())
            ->method('attempt')
        ;

        $rateLimitMiddleware = $this->createMiddleware(
            $rateLimiter,
            $this->createClientIpResolver('127.0.0.1'),
            self::RATE_LIMIT_CONFIG,
        );

        $serverRequest = $this->requestWithRouteOptions([
            'rate_limit' => [[
                'key'      => 'test_json_action',
                'by'       => ['ip'],
                'response' => 'json',
                'methods'  => ['POST'],
            ]],
        ], 'GET');

        $rateLimitCapturingHandler = new RateLimitCapturingHandler();
        $rateLimitMiddleware->process($serverRequest, $rateLimitCapturingHandler);

        self::assertTrue($rateLimitCapturingHandler->called);
    }

    #[Test]
    public function buildsKeyWithBodyFieldStrategy(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::once())
            ->method('attempt')
            ->with(
                self::equalTo('login:ip:127.0.0.1:body:email:user@example.com'),
                5,
                900,
            )
            ->willReturn(true)
        ;

        $rateLimitMiddleware = $this->createMiddleware(
            $rateLimiter,
            $this->createClientIpResolver('127.0.0.1'),
            self::RATE_LIMIT_CONFIG,
        );

        $serverRequest = $this->requestWithRouteOptions([
            'rate_limit' => [[
                'key'      => 'login',
                'by'       => ['ip', 'body:email'],
                'response' => 'json',
            ]],
        ])
            ->withParsedBody([
                'email' => 'user@example.com',
            ])
        ;

        $rateLimitCapturingHandler = new RateLimitCapturingHandler();
        $rateLimitMiddleware->process($serverRequest, $rateLimitCapturingHandler);

        self::assertTrue($rateLimitCapturingHandler->called);
    }

    #[Test]
    public function buildsKeyWithCurrentUserIdStrategy(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::once())
            ->method('attempt')
            ->with(
                self::equalTo('login:user:user-123'),
                5,
                900,
            )
            ->willReturn(true)
        ;

        $rateLimitMiddleware = $this->createMiddleware(
            $rateLimiter,
            $this->createClientIpResolver('127.0.0.1'),
            self::RATE_LIMIT_CONFIG,
        );

        $serverRequest = $this->requestWithRouteOptions([
            'rate_limit' => [[
                'key'      => 'login',
                'by'       => ['user'],
                'response' => 'json',
            ]],
        ])->withAttribute(RequestContextAttributes::CURRENT_USER_ID, 'user-123');

        $rateLimitCapturingHandler = new RateLimitCapturingHandler();
        $rateLimitMiddleware->process($serverRequest, $rateLimitCapturingHandler);

        self::assertTrue($rateLimitCapturingHandler->called);
    }

    #[Test]
    public function skipsWhenPolicyNotFoundInConfig(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::never())
            ->method('attempt')
        ;

        $rateLimitMiddleware = $this->createMiddleware(
            $rateLimiter,
            $this->createClientIpResolver('127.0.0.1'),
            self::RATE_LIMIT_CONFIG,
        );

        $serverRequest = $this->requestWithRouteOptions([
            'rate_limit' => [[
                'key'      => 'nonexistent_policy',
                'by'       => ['ip'],
                'response' => 'json',
            ]],
        ]);

        $rateLimitCapturingHandler = new RateLimitCapturingHandler();
        $rateLimitMiddleware->process($serverRequest, $rateLimitCapturingHandler);

        self::assertTrue($rateLimitCapturingHandler->called);
    }

    #[Test]
    public function usesDefaultMessageWhenNotConfigured(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::once())
            ->method('attempt')
            ->willReturn(false)
        ;

        $rateLimitMiddleware = $this->createMiddleware(
            $rateLimiter,
            $this->createClientIpResolver('127.0.0.1'),
            self::RATE_LIMIT_CONFIG,
        );

        $serverRequest = $this->requestWithRouteOptions([
            'rate_limit' => [[
                'key'      => 'test_json_action',
                'by'       => ['ip'],
                'response' => 'json',
                // no message provided
            ]],
        ]);

        $rateLimitCapturingHandler = new RateLimitCapturingHandler();
        $response                  = $rateLimitMiddleware->process($serverRequest, $rateLimitCapturingHandler);

        self::assertSame(429, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('Too many requests', $body);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function requestWithRouteOptions(array $options, string $method = 'POST'): ServerRequestInterface
    {
        $route = new Route(
            '/test',
            new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }
            },
            ['GET', 'POST'],
            'test.route',
        );
        $route->setOptions($options);

        $routeResult = RouteResult::fromRoute($route, []);

        return new ServerRequest([], [], '/test', $method)
            ->withAttribute(RouteResult::class, $routeResult)
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, new TestInertia())
        ;
    }

    /**
     * @param array<string, mixed> $rateLimitConfig
     */
    private function createMiddleware(
        RateLimiterInterface $rateLimiter,
        ClientIpResolverInterface $clientIpResolver,
        array $rateLimitConfig,
    ): RateLimitMiddleware {
        return new RateLimitMiddleware(
            $rateLimiter,
            $clientIpResolver,
            new InertiaNotificationMessenger(),
            $rateLimitConfig,
        );
    }

    private function createRateLimiter(bool $expectsAttempt = false, bool $returns = true): RateLimiterInterface
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);

        if ($expectsAttempt) {
            $rateLimiter->expects(self::once())
                ->method('attempt')
                ->willReturn($returns)
            ;
        } else {
            $rateLimiter->expects(self::never())
                ->method('attempt')
            ;
        }

        return $rateLimiter;
    }

    private function createClientIpResolver(string $ip): ClientIpResolverInterface
    {
        $resolver = $this->createMock(ClientIpResolverInterface::class);
        $resolver->method('resolve')->willReturn($ip);

        return $resolver;
    }
}

final class RateLimitCapturingHandler implements RequestHandlerInterface
{
    public bool $called = false;

    public function handle(ServerRequestInterface $request): EmptyResponse
    {
        $this->called = true;

        return new EmptyResponse(204);
    }
}
