<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Middleware\HtmlCacheControlMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(HtmlCacheControlMiddleware::class)]
final class HtmlCacheControlMiddlewareTest extends TestCase
{
    #[Test]
    public function addsNoCacheHeadersToHtmlResponses(): void
    {
        $response = $this->process(new HtmlResponse('<html></html>'));

        self::assertSame('no-cache, must-revalidate', $response->getHeaderLine('Cache-Control'));
        self::assertSame('no-cache', $response->getHeaderLine('Pragma'));
    }

    #[Test]
    public function keepsExistingCacheControlHeader(): void
    {
        $response = $this->process(
            new HtmlResponse('<html></html>', 200, [
                'Cache-Control' => 'private, max-age=60',
            ]),
        );

        self::assertSame('private, max-age=60', $response->getHeaderLine('Cache-Control'));
        self::assertFalse($response->hasHeader('Pragma'));
    }

    #[Test]
    public function leavesNonHtmlResponsesUntouched(): void
    {
        $response = $this->process(new JsonResponse([
            'ok' => true,
        ]));

        self::assertFalse($response->hasHeader('Cache-Control'));
        self::assertFalse($response->hasHeader('Pragma'));
    }

    private function process(ResponseInterface $response): ResponseInterface
    {
        return new HtmlCacheControlMiddleware()->process(
            new ServerRequest([], [], '/'),
            new StaticResponseHandler($response),
        );
    }
}

final readonly class StaticResponseHandler implements RequestHandlerInterface
{
    public function __construct(private ResponseInterface $response) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}
