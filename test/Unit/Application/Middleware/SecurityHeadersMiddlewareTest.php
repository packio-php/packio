<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(SecurityHeadersMiddleware::class)]
final class SecurityHeadersMiddlewareTest extends TestCase
{
    #[Test]
    public function addsSecurityHeadersToEveryResponse(): void
    {
        foreach ([
            new HtmlResponse('<html></html>'), new JsonResponse([
                'ok' => true,
            ])] as $original) {
            $response = $this->process($original);

            self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
            self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
            self::assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
        }
    }

    #[Test]
    public function keepsHeadersSetByHandler(): void
    {
        $response = $this->process(
            new HtmlResponse('', 200, [
                'X-Frame-Options' => 'SAMEORIGIN',
                'Referrer-Policy' => 'no-referrer',
            ]),
        );

        self::assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    private function process(ResponseInterface $response): ResponseInterface
    {
        return new SecurityHeadersMiddleware()->process(
            new ServerRequest([], [], '/'),
            new readonly class($response) implements RequestHandlerInterface {
                public function __construct(private ResponseInterface $response) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->response;
                }
            },
        );
    }
}
