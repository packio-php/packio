<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\Middleware;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Rest\Infrastructure\Middleware\ApiAuditMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(ApiAuditMiddleware::class)]
final class ApiAuditMiddlewareTest extends TestCase
{
    #[Test]
    public function itReturnsTheHandlerResponseWhenAuditRecordingFails(): void
    {
        $jsonResponse = new JsonResponse([], 201);
        $audit        = $this->createStub(AuditApiFacadeInterface::class);
        $audit->method('record')->willThrowException(new RuntimeException('db down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('API audit record failed.', self::arrayHasKey('exception'))
        ;

        $response = new ApiAuditMiddleware($audit, $logger)
            ->process(new ServerRequest([], [], '/api/v1/x', 'POST'), $this->handler($jsonResponse))
        ;

        self::assertSame($jsonResponse, $response);
    }

    #[Test]
    public function itDoesNotRecordOrLogForFailedOrReadRequests(): void
    {
        $audit = $this->createMock(AuditApiFacadeInterface::class);
        $audit->expects(self::never())->method('record');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        $apiAuditMiddleware = new ApiAuditMiddleware($audit, $logger);

        $apiAuditMiddleware->process(new ServerRequest([], [], '/api/v1/x', 'POST'), $this->handler(new JsonResponse([], 422)));
        $apiAuditMiddleware->process(new ServerRequest([], [], '/api/v1/x', 'GET'), $this->handler(new JsonResponse([], 200)));
    }

    #[Test]
    public function itRecordsSuccessfulWrites(): void
    {
        $audit = $this->createMock(AuditApiFacadeInterface::class);
        $audit->expects(self::once())->method('record')->with('api.request');

        new ApiAuditMiddleware($audit, $this->createStub(LoggerInterface::class))
            ->process(new ServerRequest([], [], '/api/v1/x', 'PATCH'), $this->handler(new JsonResponse([], 200)))
        ;
    }

    #[Test]
    public function itRecordsCanonicalSourceAndOrganizationIds(): void
    {
        $uuid  = '0b9a4c3e-1d2f-4a5b-8c6d-7e8f9a0b1c2d';
        $audit = $this->createMock(AuditApiFacadeInterface::class);
        $audit->expects(self::once())->method('record')->with(
            'api.request',
            self::anything(),
            $uuid,
            $uuid,
        );

        $serverRequest = new ServerRequest([], [], '/api/v1/x', 'POST')
            ->withAttribute('sourceId', '{' . $uuid . '}')
            ->withAttribute('organizationId', 'urn:uuid:' . $uuid)
        ;

        new ApiAuditMiddleware($audit, $this->createStub(LoggerInterface::class))
            ->process($serverRequest, $this->handler(new JsonResponse([], 200)))
        ;
    }

    #[Test]
    public function itRecordsANonUuidSourceIdRaw(): void
    {
        $audit = $this->createMock(AuditApiFacadeInterface::class);
        $audit->expects(self::once())->method('record')->with(
            'api.request',
            self::anything(),
            self::anything(),
            'not-a-uuid',
        );

        new ApiAuditMiddleware($audit, $this->createStub(LoggerInterface::class))
            ->process(
                new ServerRequest([], [], '/api/v1/x', 'POST')->withAttribute('sourceId', 'not-a-uuid'),
                $this->handler(new JsonResponse([], 200)),
            )
        ;
    }

    private function handler(ResponseInterface $response): RequestHandlerInterface
    {
        return new readonly class($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }
}
