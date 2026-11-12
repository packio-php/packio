<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\ImplicitHeadMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Packio\Application\Router\ImplicitHeadRouterAdapter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function in_array;

final readonly class OperationalProbeDispatchMiddleware implements MiddlewareInterface
{
    private MiddlewarePipe $middlewarePipe;

    public function __construct(
        private ImplicitHeadRouterAdapter $implicitHeadRouterAdapter,
        MiddlewareInterface $registrySubdomainMiddleware,
        StreamFactoryInterface $streamFactory,
        DispatchMiddleware $dispatchMiddleware,
    ) {
        $this->middlewarePipe = new MiddlewarePipe();
        $this->middlewarePipe->pipe(new RouteMiddleware($this->implicitHeadRouterAdapter));
        $this->middlewarePipe->pipe($registrySubdomainMiddleware);
        $this->middlewarePipe->pipe(new ImplicitHeadMiddleware($this->implicitHeadRouterAdapter, $streamFactory));
        $this->middlewarePipe->pipe($dispatchMiddleware);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $handler->handle($request);
        }

        $routeResult = $this->implicitHeadRouterAdapter->match($request);
        if ('HEAD' === $request->getMethod() && $routeResult->isFailure()) {
            $routeResult = $this->implicitHeadRouterAdapter->match($request->withMethod('GET'));
        }

        if (! $routeResult->isSuccess()
            || ! in_array($routeResult->getMatchedRouteName(), ['health', 'ready'], true)
        ) {
            return $handler->handle($request);
        }

        return $this->middlewarePipe->handle($request);
    }
}
