<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Middleware\StripPackageMappingErrorInertiaDecorationMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(StripPackageMappingErrorInertiaDecorationMiddleware::class)]
final class StripPackageMappingErrorInertiaDecorationMiddlewareTest extends TestCase
{
    #[Test]
    public function itRemovesTheInternalMarkerAndInertiaDecorationFromFallbackResponses(): void
    {
        $response = $this->process(
            new JsonResponse([
                'error' => 'Mapping failed',
            ], 422)
                ->withHeader(StripPackageMappingErrorInertiaDecorationMiddleware::INERTIA_FALLBACK_RESPONSE_MARKER, 'true')
                ->withHeader('X-Inertia', 'true')
                ->withHeader('X-Inertia-Version', 'release')
                ->withHeader('Vary', 'Origin, X-Inertia')
        );

        self::assertFalse($response->hasHeader(StripPackageMappingErrorInertiaDecorationMiddleware::INERTIA_FALLBACK_RESPONSE_MARKER));
        self::assertFalse($response->hasHeader('X-Inertia'));
        self::assertFalse($response->hasHeader('X-Inertia-Version'));
        self::assertSame('Origin', $response->getHeaderLine('Vary'));
    }

    #[Test]
    public function itLeavesNonFallbackResponsesUntouched(): void
    {
        $response = $this->process(
            new JsonResponse([
                'ok' => true,
            ])
                ->withHeader('X-Inertia', 'true')
                ->withHeader('Vary', 'X-Inertia')
        );

        self::assertTrue($response->hasHeader('X-Inertia'));
        self::assertSame('X-Inertia', $response->getHeaderLine('Vary'));
    }

    private function process(ResponseInterface $response): ResponseInterface
    {
        return new StripPackageMappingErrorInertiaDecorationMiddleware()->process(
            new ServerRequest(),
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
