<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function str_contains;
use function strtolower;

final readonly class HtmlCacheControlMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if ($response->hasHeader('Cache-Control')) {
            return $response;
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        return $response
            ->withHeader('Cache-Control', 'no-cache, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
        ;
    }
}
