<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Laminas\Stratigility\Exception\EmptyPipelineException;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Packio\Application\Middleware\OperationalProbeDispatchMiddleware;
use Packio\Application\Router\ImplicitHeadRouterAdapter;
use Packio\Registry\Application\Service\RegistryOrganizationResolver;
use Packio\Registry\Infrastructure\Middleware\RestrictRegistrySubdomainMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Router\RadixRouter;

#[CoversClass(OperationalProbeDispatchMiddleware::class)]
final class OperationalProbeDispatchMiddlewareTest extends TestCase
{
    #[Test]
    public function dispatchesNamedProbesThroughNativePipeline(): void
    {
        foreach (['health', 'ready'] as $name) {
            [$middleware, $routeMiddleware, $requestHandler] = $this->fixture($name, '/custom-probe/:id');
            $next                                            = new ProbeCapturingHandler();

            $response = $middleware->process(new ServerRequest(uri: 'https://packio.us/custom-probe/42'), $next);

            self::assertSame(1, $routeMiddleware->calls);
            self::assertSame(1, $requestHandler->calls);
            self::assertSame(0, $next->calls);
            self::assertSame('called', $response->getHeaderLine('X-Route-Middleware'));
            self::assertSame('42', $requestHandler->request?->getAttribute('id'));
        }
    }

    #[Test]
    public function passesOriginalRequestForMissingAndUnrelatedRoutes(): void
    {
        [$middleware] = $this->fixture('other', '/unrelated');
        foreach ([
            new ServerRequest(uri: 'https://packio.us/missing'),
            new ServerRequest(uri: 'https://packio.us/unrelated'),
            new ServerRequest(uri: 'https://packio.us/custom-probe?x=1', method: 'POST'),
            new ServerRequest(uri: 'https://packio.us/custom-probe', method: 'OPTIONS'),
        ] as $request) {
            $request = $request->withAttribute('custom.attribute', 'kept');
            $next    = new ProbeCapturingHandler();

            $middleware->process($request, $next);

            self::assertSame($request, $next->request);
            self::assertSame('kept', $next->request->getAttribute('custom.attribute'));
            self::assertNull($next->request->getAttribute('route.attribute'));
        }

        $forged = new ServerRequest(uri: 'https://packio.us/missing')->withAttribute(
            RouteResult::class,
            RouteResult::fromRoute(new Route('/ready', new ProbeRouteMiddleware(), ['GET'], 'health')),
        );
        $forgedNext = new ProbeCapturingHandler();
        $middleware->process($forged, $forgedNext);
        self::assertSame($forged, $forgedNext->request, 'An incoming RouteResult never determines selection.');

        [$postOnlyMiddleware] = $this->fixture('post-only', '/post-only', ['POST']);
        $methodMismatch       = new ServerRequest(uri: 'https://packio.us/post-only', method: 'GET');
        $methodNext           = new ProbeCapturingHandler();
        $postOnlyMiddleware->process($methodMismatch, $methodNext);
        self::assertSame($methodMismatch, $methodNext->request, 'A method mismatch leaves the original request untouched.');

        [$healthPathMiddleware] = $this->fixture('custom-health', '/health');
        $healthPath             = new ServerRequest(uri: 'https://packio.us/health');
        $healthNext             = new ProbeCapturingHandler();
        $healthPathMiddleware->process($healthPath, $healthNext);
        self::assertSame($healthPath, $healthNext->request, 'A probe path with a different route name is not selected.');
    }

    #[Test]
    public function implicitHeadUsesNativeForwardingAndExplicitHeadKeepsPriority(): void
    {
        [$implicitMiddleware, $implicitRouteMiddleware, $implicitHandler] = $this->fixture('ready', '/ready', ['GET']);
        $response                                                         = $implicitMiddleware->process(new ServerRequest(uri: 'https://packio.us/ready', method: 'HEAD'), new ProbeCapturingHandler());

        self::assertSame('GET', $implicitHandler->request?->getMethod());
        self::assertSame('HEAD', $implicitHandler->request->getAttribute('forwarded_http_method'));
        self::assertSame('', (string) $response->getBody());

        [$explicitMiddleware, , $explicitHandler] = $this->fixture('health', '/explicit', ['GET'], ['HEAD']);
        $response                                 = $explicitMiddleware->process(new ServerRequest(uri: 'https://packio.us/explicit', method: 'HEAD'), new ProbeCapturingHandler());

        self::assertSame('HEAD', $explicitHandler->request?->getMethod());
        self::assertSame('called', $response->getHeaderLine('X-Route-Middleware'));

        $implicitMiddleware->process(new ServerRequest(uri: 'https://packio.us/ready'), new ProbeCapturingHandler());
        self::assertSame(2, $implicitRouteMiddleware->calls, 'A selector instance can handle repeated GET and HEAD probe requests.');

        $countingRadixRouter = new CountingRadixRouter();
        $countingRadixRouter->addRoute(new Route('/ready', new ProbeRouteMiddleware(), ['GET', 'HEAD'], 'other-head'));
        $operationalProbeDispatchMiddleware     = $this->middleware(new ImplicitHeadRouterAdapter($countingRadixRouter));
        $serverRequest                          = new ServerRequest(uri: 'https://packio.us/ready', method: 'HEAD');
        $probeCapturingHandler                  = new ProbeCapturingHandler();
        $operationalProbeDispatchMiddleware->process($serverRequest, $probeCapturingHandler);
        self::assertSame($serverRequest, $probeCapturingHandler->request, 'An explicit HEAD route with another name wins over a GET probe candidate.');
        self::assertSame(['HEAD'], $countingRadixRouter->observedMethods, 'A successful explicit HEAD match must not trigger a GET candidate lookup.');
    }

    #[Test]
    public function registryRestrictionRunsOnlyInsideSelectedGetAndHeadBranch(): void
    {
        foreach (['GET', 'HEAD'] as $method) {
            [$middleware, , $probeHandler] = $this->fixture('ready', '/ready');
            $response                      = $middleware->process(
                new ServerRequest(uri: 'https://acme.packio.localhost/ready?source=probe', method: $method),
                new ProbeCapturingHandler(),
            );

            self::assertSame(302, $response->getStatusCode());
            self::assertSame('http://localhost:8080/ready?source=probe', $response->getHeaderLine('Location'));
            self::assertSame(0, $probeHandler->calls);
        }

        [$middleware]    = $this->fixture('ready', '/ready');
        $inertiaResponse = $middleware->process(
            new ServerRequest(uri: 'https://acme.packio.localhost/ready?source=probe', headers: [
                'X-Inertia' => 'true',
            ]),
            new ProbeCapturingHandler(),
        );
        self::assertSame(409, $inertiaResponse->getStatusCode());
        self::assertSame('http://localhost:8080/ready?source=probe', $inertiaResponse->getHeaderLine('X-Inertia-Location'));

        [$middleware]                  = $this->fixture('ready', '/ready');
        $serverRequest                 = new ServerRequest(uri: 'https://acme.packio.localhost/ready', method: 'POST');
        $probeCapturingHandler         = new ProbeCapturingHandler();
        $middleware->process($serverRequest, $probeCapturingHandler);

        self::assertSame($serverRequest, $probeCapturingHandler->request, 'Non-probe methods bypass early registry restriction.');
    }

    #[Test]
    public function selectedProbeCannotFallThroughToMainStack(): void
    {
        $radixRouter               = new RadixRouter();
        $implicitHeadRouterAdapter = new ImplicitHeadRouterAdapter($radixRouter);
        $implicitHeadRouterAdapter->addRoute(new Route('/ready', new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        }, ['GET'], 'ready'));
        $middleware                  = $this->middleware($implicitHeadRouterAdapter);
        $probeCapturingHandler       = new ProbeCapturingHandler();

        try {
            $middleware->process(new ServerRequest(uri: 'https://packio.us/ready'), $probeCapturingHandler);
            self::fail('The closed probe pipeline must throw when its selected route delegates.');
        } catch (EmptyPipelineException) {
            self::assertSame(0, $probeCapturingHandler->calls);
        }
    }

    #[Test]
    public function methodsOtherThanGetAndHeadSkipRouterLookup(): void
    {
        foreach (['POST', 'OPTIONS'] as $method) {
            $router     = new CountingRadixRouter();
            $middleware = $this->middleware(new ImplicitHeadRouterAdapter($router));
            $request    = new ServerRequest(uri: 'https://packio.us/ready', method: $method);
            $next       = new ProbeCapturingHandler();

            $middleware->process($request, $next);

            self::assertSame(0, $router->matchCalls);
            self::assertSame($request, $next->request);
        }
    }

    /**
     * @param non-empty-string $path
     * @param list<string>     $methods
     * @param list<string>     $extraMethods
     *
     * @return array{OperationalProbeDispatchMiddleware, ProbeRouteMiddleware, ProbeCapturingHandler}
     */
    private function fixture(string $name, string $path, array $methods = ['GET'], array $extraMethods = []): array
    {
        $radixRouter                       = new RadixRouter();
        $implicitHeadRouterAdapter         = new ImplicitHeadRouterAdapter($radixRouter);
        $probeRouteMiddleware              = new ProbeRouteMiddleware();
        $handler                           = $probeRouteMiddleware->handler;
        $implicitHeadRouterAdapter->addRoute(new Route($path, $probeRouteMiddleware, [...$methods, ...$extraMethods], $name));

        return [$this->middleware($implicitHeadRouterAdapter), $probeRouteMiddleware, $handler];
    }

    private function middleware(ImplicitHeadRouterAdapter $implicitHeadRouterAdapter): OperationalProbeDispatchMiddleware
    {
        $brandingConfig               = new BrandingConfig();
        $registryOrganizationResolver = new RegistryOrganizationResolver($brandingConfig);

        return new OperationalProbeDispatchMiddleware(
            $implicitHeadRouterAdapter,
            new RestrictRegistrySubdomainMiddleware($registryOrganizationResolver, $brandingConfig),
            new StreamFactory(),
            new DispatchMiddleware(),
        );
    }
}

final class ProbeRouteMiddleware implements MiddlewareInterface
{
    public int $calls = 0;
    public ProbeCapturingHandler $handler;

    public function __construct()
    {
        $this->handler = new ProbeCapturingHandler();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        ++$this->calls;

        return $this->handler->handle($request)->withHeader('X-Route-Middleware', 'called');
    }
}

final class ProbeCapturingHandler implements RequestHandlerInterface
{
    public int $calls                       = 0;
    public ?ServerRequestInterface $request = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ++$this->calls;
        $this->request = $request;

        return new Response(body: new StreamFactory()->createStream($request->getMethod()));
    }
}

final class CountingRadixRouter extends RadixRouter
{
    public int $matchCalls = 0;

    /** @var list<string> */
    public array $observedMethods = [];

    public function match(ServerRequestInterface $request): RouteResult
    {
        ++$this->matchCalls;
        $this->observedMethods[] = $request->getMethod();

        return parent::match($request);
    }
}
