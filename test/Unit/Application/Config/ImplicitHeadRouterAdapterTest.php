<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Config;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Route;
use Packio\Application\Middleware\OperationalProbeDispatchMiddleware;
use Packio\Application\Router\ImplicitHeadRouterAdapter;
use Packio\Registry\Application\Service\RegistryOrganizationResolver;
use Packio\Registry\Infrastructure\Middleware\RestrictRegistrySubdomainMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Router\RadixRouter;

final class ImplicitHeadRouterAdapterTest extends TestCase
{
    #[Test]
    public function probeAdapterRestoresNativeImplicitHeadBehaviorWithoutChangingRawRouter(): void
    {
        $radixRouter                        = new RadixRouter();
        $implicitHeadRouterAdapter          = new ImplicitHeadRouterAdapter($radixRouter);
        $handler                            = new class implements MiddlewareInterface {
            /** @var list<string> */
            public array $observedMethods = [];

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->observedMethods[] = $request->getMethod();

                return new Response(body: new StreamFactory()->createStream($request->getMethod()));
            }
        };
        $implicitHeadRouterAdapter->addRoute(new Route('/ready', $handler, ['GET'], 'ready'));
        $implicitHeadRouterAdapter->addRoute(new Route('/explicit', $handler, ['GET', 'HEAD'], 'health'));

        $serverRequest = new ServerRequest(uri: 'https://packio.us/ready', method: 'HEAD');
        self::assertTrue($radixRouter->match($serverRequest)->isSuccess(), 'The shared raw router keeps its normal HEAD match.');
        self::assertTrue($implicitHeadRouterAdapter->match($serverRequest)->isFailure(), 'Only the local probe adapter converts implicit HEAD matches.');

        $brandingConfig                     = new BrandingConfig();
        $operationalProbeDispatchMiddleware = new OperationalProbeDispatchMiddleware(
            $implicitHeadRouterAdapter,
            new RestrictRegistrySubdomainMiddleware(
                new RegistryOrganizationResolver($brandingConfig),
                $brandingConfig,
            ),
            new StreamFactory(),
            new DispatchMiddleware(),
        );

        $response = $operationalProbeDispatchMiddleware->process(
            new ServerRequest(uri: 'https://packio.us/ready', method: 'HEAD'),
            new AdapterFallbackHandler(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertSame(['GET'], $handler->observedMethods, 'Implicit HEAD must reach the GET route as GET.');

        $explicitResponse = $operationalProbeDispatchMiddleware->process(
            new ServerRequest(uri: 'https://packio.us/explicit', method: 'HEAD'),
            new AdapterFallbackHandler(),
        );

        self::assertSame(200, $explicitResponse->getStatusCode());
        self::assertSame('HEAD', (string) $explicitResponse->getBody());
        self::assertSame(['GET', 'HEAD'], $handler->observedMethods, 'An explicit HEAD route must retain the HEAD method.');
    }

    #[Test]
    public function onlyImplicitHeadMatchesAreConvertedToFailures(): void
    {
        $implicitHeadRouterAdapter = new ImplicitHeadRouterAdapter(new RadixRouter());
        $implicitHeadRouterAdapter->addRoute(new Route('/implicit', new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response();
            }
        }, ['GET'], 'implicit'));
        $implicitHeadRouterAdapter->addRoute(new Route('/explicit', new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response();
            }
        }, ['GET', 'HEAD'], 'explicit'));

        self::assertTrue($implicitHeadRouterAdapter->match(new ServerRequest(uri: 'https://packio.us/implicit', method: 'HEAD'))->isFailure());
        self::assertTrue($implicitHeadRouterAdapter->match(new ServerRequest(uri: 'https://packio.us/explicit', method: 'HEAD'))->isSuccess());
        self::assertTrue($implicitHeadRouterAdapter->match(new ServerRequest(uri: 'https://packio.us/missing', method: 'HEAD'))->isFailure());
        self::assertTrue($implicitHeadRouterAdapter->match(new ServerRequest(uri: 'https://packio.us/implicit', method: 'POST'))->isFailure());
        self::assertSame('implicit', $implicitHeadRouterAdapter->match(new ServerRequest(uri: 'https://packio.us/implicit', method: 'GET'))->getMatchedRouteName());
    }
}

final class AdapterFallbackHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(status: 404);
    }
}
