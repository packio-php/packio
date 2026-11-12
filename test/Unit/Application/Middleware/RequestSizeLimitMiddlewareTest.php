<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Common\Infrastructure\Resource\ResourceLimits;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Utils;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use Packio\Application\Middleware\RequestSizeLimitMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestSizeLimitMiddlewareTest extends TestCase
{
    public function testRejectsDeclaredOversizeWithoutReadingBody(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body->expects(self::never())->method('read');
        $serverRequest = new ServerRequest()->withUri(new Uri('/webhooks/github/source'))->withBody($body)->withHeader('Content-Length', '9');
        self::assertSame(413, $this->middleware()->process($serverRequest, $this->neverCalled())->getStatusCode());
    }

    public function testRejectsUnknownSizeChunkedBodyWithoutTrustingContentLength(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body->method('getSize')->willReturn(null);
        $body->method('isSeekable')->willReturn(false);
        $body->method('eof')->willReturn(false);
        $body->expects(self::once())->method('read')->with(9)->willReturn('123456789');
        $serverRequest = new ServerRequest()->withUri(new Uri('/webhooks/github/source'))->withBody($body)->withHeader('Content-Length', '1');
        self::assertSame(413, $this->middleware()->process($serverRequest, $this->neverCalled())->getStatusCode());
    }

    public function testNonSeekableBodyAtLimitIsPreservedForHandler(): void
    {
        $serverRequest = new ServerRequest()->withUri(new Uri('/webhooks/github/source'))->withBody(new NoSeekStream(Utils::streamFor('12345678')));
        $handler       = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturnCallback(static function($request) {
            self::assertSame('12345678', (string) $request->getBody());

            return new EmptyResponse(204);
        });
        self::assertSame(204, $this->middleware()->process($serverRequest, $handler)->getStatusCode());
    }

    public function testOtherRoutesUseGeneralLimit(): void
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn(new EmptyResponse());
        self::assertSame(204, $this->middleware()->process(new ServerRequest()->withBody(Utils::streamFor('123456789')), $handler)->getStatusCode());
    }

    private function middleware(): RequestSizeLimitMiddleware
    {
        return new RequestSizeLimitMiddleware(new ResourceLimits(webhookBytes: 8, requestBytes: 16));
    }

    private function neverCalled(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        return $handler;
    }
}
