<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Config;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\HttpHandlerRunner\RequestHandlerRunnerInterface;
use Laminas\Stratigility\Middleware\ErrorHandler;
use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Application;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Handler\NotFoundHandler;
use Mezzio\Helper\BodyParams\BodyParamsMiddleware;
use Mezzio\Helper\ServerUrlMiddleware;
use Mezzio\Helper\UrlHelperMiddleware;
use Mezzio\MiddlewareFactoryInterface;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\ImplicitHeadMiddleware;
use Mezzio\Router\Middleware\ImplicitOptionsMiddleware;
use Mezzio\Router\Middleware\MethodNotAllowedMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Session\SessionMiddleware;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Middleware\ApiAuthenticationAttemptRateLimitMiddleware;
use Packio\Application\Middleware\CsrfValidationMiddleware;
use Packio\Application\Middleware\HandleAuthorizationExceptionMiddleware;
use Packio\Application\Middleware\HandleInertiaRequests;
use Packio\Application\Middleware\HtmlCacheControlMiddleware;
use Packio\Application\Middleware\OperationalProbeDispatchMiddleware;
use Packio\Application\Middleware\OptionalRouteAuthenticationMiddleware;
use Packio\Application\Middleware\RefreshAuthenticatedSessionMiddleware;
use Packio\Application\Middleware\RequestSizeLimitMiddleware;
use Packio\Application\Middleware\RequireEmailVerificationMiddleware;
use Packio\Application\Middleware\RequireUnbannedUserMiddleware;
use Packio\Application\Middleware\ResolveCurrentMembershipMiddleware;
use Packio\Application\Middleware\ResolveCurrentOrganizationMiddleware;
use Packio\Application\Middleware\ResolveCurrentUserMiddleware;
use Packio\Application\Middleware\ResolveOrganizationActorContextMiddleware;
use Packio\Application\Middleware\RuntimeCorsMiddleware;
use Packio\Application\Middleware\SecurityHeadersMiddleware;
use Packio\Application\Middleware\StripPackageMappingErrorInertiaDecorationMiddleware;
use Packio\Registry\Infrastructure\Middleware\ResolveRegistryOrganizationAttributeMiddleware;
use Packio\Registry\Infrastructure\Middleware\RestrictRegistrySubdomainMiddleware;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\SentryPsr\Middleware\SentryErrorMiddleware;

use function array_search;
use function dirname;
use function is_int;
use function sprintf;

#[AllowMockObjectsWithoutExpectations]
final class PipelineOrderTest extends TestCase
{
    #[Test]
    public function notificationProducersRunAfterTheInertiaRequestAttributeIsAttached(): void
    {
        $configuredMiddleware = [];
        $middlewareFactory    = $this->createMock(MiddlewareFactoryInterface::class);
        $middlewareFactory->method('prepare')->willReturnCallback(
            static function(mixed $middleware) use (&$configuredMiddleware): MiddlewareInterface {
                $configuredMiddleware[] = $middleware;

                return new class implements MiddlewareInterface {
                    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                    {
                        return new EmptyResponse();
                    }
                };
            },
        );
        $application = new Application(
            $middlewareFactory,
            new MiddlewarePipe(),
            $this->createMock(RouteCollectorInterface::class),
            $this->createMock(RequestHandlerRunnerInterface::class),
        );
        $pipeline    = require dirname(__DIR__, 4) . '/config/pipeline.php';

        $pipeline(
            $application,
            $middlewareFactory,
            $this->createMock(ContainerInterface::class),
        );

        self::assertLessThan(
            $this->indexOf(BodyParamsMiddleware::class, $configuredMiddleware),
            $this->indexOf(RequestSizeLimitMiddleware::class, $configuredMiddleware),
        );
        self::assertLessThan(
            $this->indexOf(FlashMessageMiddleware::class, $configuredMiddleware),
            $this->indexOf(SessionMiddleware::class, $configuredMiddleware),
        );
        self::assertLessThan(
            $this->indexOf(InertiaMiddleware::class, $configuredMiddleware),
            $this->indexOf(StripPackageMappingErrorInertiaDecorationMiddleware::class, $configuredMiddleware),
        );
        self::assertLessThan(
            $this->indexOf(InertiaMiddleware::class, $configuredMiddleware),
            $this->indexOf(FlashMessageMiddleware::class, $configuredMiddleware),
        );
    }

    #[Test]
    public function routeAwareOptionalAuthenticationRunsAfterRouting(): void
    {
        $configuredMiddleware = [];
        $middlewareFactory    = $this->createMock(MiddlewareFactoryInterface::class);
        $middlewareFactory->method('prepare')->willReturnCallback(
            static function(mixed $middleware) use (&$configuredMiddleware): MiddlewareInterface {
                $configuredMiddleware[] = $middleware;

                return new class implements MiddlewareInterface {
                    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                    {
                        return $handler->handle($request);
                    }
                };
            },
        );
        $application = new Application(
            $middlewareFactory,
            new MiddlewarePipe(),
            $this->createMock(RouteCollectorInterface::class),
            $this->createMock(RequestHandlerRunnerInterface::class),
        );
        $pipeline    = require dirname(__DIR__, 4) . '/config/pipeline.php';

        $pipeline($application, $middlewareFactory, $this->createMock(ContainerInterface::class));

        self::assertLessThan(
            $this->indexOf(OptionalRouteAuthenticationMiddleware::class, $configuredMiddleware),
            $this->indexOf(RouteMiddleware::class, $configuredMiddleware),
        );
        self::assertLessThan(
            $this->indexOf(OptionalRouteAuthenticationMiddleware::class, $configuredMiddleware),
            $this->indexOf(ApiAuthenticationAttemptRateLimitMiddleware::class, $configuredMiddleware),
        );
    }

    #[Test]
    public function banGateRunsBeforeVerificationAndOrganizationResolution(): void
    {
        $configuredMiddleware = [];
        $middlewareFactory    = $this->createMock(MiddlewareFactoryInterface::class);
        $middlewareFactory->method('prepare')->willReturnCallback(
            static function(mixed $middleware) use (&$configuredMiddleware): MiddlewareInterface {
                $configuredMiddleware[] = $middleware;

                return new class implements MiddlewareInterface {
                    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                    {
                        return $handler->handle($request);
                    }
                };
            },
        );
        $application = new Application(
            $middlewareFactory,
            new MiddlewarePipe(),
            $this->createMock(RouteCollectorInterface::class),
            $this->createMock(RequestHandlerRunnerInterface::class),
        );
        $pipeline    = require dirname(__DIR__, 4) . '/config/pipeline.php';

        $pipeline($application, $middlewareFactory, $this->createMock(ContainerInterface::class));

        self::assertLessThan(
            $this->indexOf(RequireUnbannedUserMiddleware::class, $configuredMiddleware),
            $this->indexOf(ResolveCurrentUserMiddleware::class, $configuredMiddleware),
        );
        self::assertLessThan(
            $this->indexOf(RequireEmailVerificationMiddleware::class, $configuredMiddleware),
            $this->indexOf(RequireUnbannedUserMiddleware::class, $configuredMiddleware),
        );
        self::assertLessThan(
            $this->indexOf(ResolveCurrentOrganizationMiddleware::class, $configuredMiddleware),
            $this->indexOf(RequireUnbannedUserMiddleware::class, $configuredMiddleware),
        );
    }

    #[Test]
    public function operationalProbeSelectorPrecedesTheOriginalPipelineOrder(): void
    {
        $configuredMiddleware = [];
        $middlewareFactory    = $this->createMock(MiddlewareFactoryInterface::class);
        $middlewareFactory->method('prepare')->willReturnCallback(
            static function(mixed $middleware) use (&$configuredMiddleware): MiddlewareInterface {
                $configuredMiddleware[] = $middleware;

                return new class implements MiddlewareInterface {
                    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                    {
                        return $handler->handle($request);
                    }
                };
            },
        );
        $application = new Application(
            $middlewareFactory,
            new MiddlewarePipe(),
            $this->createMock(RouteCollectorInterface::class),
            $this->createMock(RequestHandlerRunnerInterface::class),
        );
        $pipeline    = require dirname(__DIR__, 4) . '/config/pipeline.php';

        $pipeline($application, $middlewareFactory, $this->createMock(ContainerInterface::class));

        self::assertSame([
            SecurityHeadersMiddleware::class,
            ErrorHandler::class,
            SentryErrorMiddleware::class,
            OperationalProbeDispatchMiddleware::class,
            RuntimeCorsMiddleware::class,
            HtmlCacheControlMiddleware::class,
            ServerUrlMiddleware::class,
            RequestSizeLimitMiddleware::class,
            BodyParamsMiddleware::class,
            RouteMiddleware::class,
            RestrictRegistrySubdomainMiddleware::class,
            SessionMiddleware::class,
            FlashMessageMiddleware::class,
            CsrfValidationMiddleware::class,
            StripPackageMappingErrorInertiaDecorationMiddleware::class,
            InertiaMiddleware::class,
            ApiAuthenticationAttemptRateLimitMiddleware::class,
            OptionalRouteAuthenticationMiddleware::class,
            RefreshAuthenticatedSessionMiddleware::class,
            ResolveCurrentUserMiddleware::class,
            RequireUnbannedUserMiddleware::class,
            RequireEmailVerificationMiddleware::class,
            ResolveRegistryOrganizationAttributeMiddleware::class,
            ResolveCurrentOrganizationMiddleware::class,
            ResolveCurrentMembershipMiddleware::class,
            ResolveOrganizationActorContextMiddleware::class,
            HandleAuthorizationExceptionMiddleware::class,
            HandleInertiaRequests::class,
            ImplicitHeadMiddleware::class,
            ImplicitOptionsMiddleware::class,
            MethodNotAllowedMiddleware::class,
            UrlHelperMiddleware::class,
            DispatchMiddleware::class,
            NotFoundHandler::class,
        ], $configuredMiddleware, 'The main pipeline must match the order anchored by the original pipeline version.');
    }

    #[Test]
    public function configuredPipelineMakesTheInertiaAttributeAvailableAtTheNotificationProducerBoundary(): void
    {
        $pipelineNotificationProducerBoundary = new PipelineNotificationProducerBoundary();
        $middlewareFactory                    = $this->createMock(MiddlewareFactoryInterface::class);
        $middlewareFactory->method('prepare')->willReturnCallback(
            static function(mixed $middleware) use ($pipelineNotificationProducerBoundary): MiddlewareInterface {
                if (InertiaMiddleware::class === $middleware) {
                    return new readonly class($pipelineNotificationProducerBoundary) implements MiddlewareInterface {
                        public function __construct(private PipelineNotificationProducerBoundary $pipelineNotificationProducerBoundary) {}

                        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                        {
                            $inertia                                             = new TestInertia();
                            $this->pipelineNotificationProducerBoundary->inertia = $inertia;

                            return $handler->handle($request->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia));
                        }
                    };
                }

                if (ApiAuthenticationAttemptRateLimitMiddleware::class === $middleware) {
                    return new readonly class($pipelineNotificationProducerBoundary) implements MiddlewareInterface {
                        public function __construct(private PipelineNotificationProducerBoundary $pipelineNotificationProducerBoundary) {}

                        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                        {
                            new InertiaNotificationMessenger()->flash($request, 'success', 'Producer boundary reached.');
                            $this->pipelineNotificationProducerBoundary->wasReached = true;

                            return new EmptyResponse();
                        }
                    };
                }

                return new class implements MiddlewareInterface {
                    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                    {
                        return $handler->handle($request);
                    }
                };
            },
        );
        $application = new Application(
            $middlewareFactory,
            new MiddlewarePipe(),
            $this->createMock(RouteCollectorInterface::class),
            $this->createMock(RequestHandlerRunnerInterface::class),
        );
        $pipeline    = require dirname(__DIR__, 4) . '/config/pipeline.php';

        $pipeline($application, $middlewareFactory, $this->createMock(ContainerInterface::class));

        $application->handle(new ServerRequest());

        self::assertTrue($pipelineNotificationProducerBoundary->wasReached);
        self::assertInstanceOf(TestInertia::class, $pipelineNotificationProducerBoundary->inertia);
        self::assertSame([
            'success' => 'Producer boundary reached.',
        ], $pipelineNotificationProducerBoundary->inertia->flash);
    }

    /** @param list<mixed> $middleware */
    private function indexOf(string $expected, array $middleware): int
    {
        $index = array_search($expected, $middleware, true);
        if (! is_int($index)) {
            self::fail(sprintf('%s must be configured in the application pipeline.', $expected));
        }

        return $index;
    }
}

final class PipelineNotificationProducerBoundary
{
    public bool $wasReached      = false;
    public ?TestInertia $inertia = null;
}
