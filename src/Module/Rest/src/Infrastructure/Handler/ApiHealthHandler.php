<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use OpenApi\Attributes as OA;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/health', name: 'api.v1.health')]
#[OA\Get(path: '/api/v1/health', operationId: 'apiHealth', security: [], tags: ['System'], responses: [
    new OA\Response(
        response: 200,
        description: 'API health status',
        content: new OA\JsonContent(
            required: ['status'],
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'OK'),
            ],
            type: 'object',
        ),
    ),
])]
final readonly class ApiHealthHandler implements RequestHandlerInterface
{
    public function __construct(private ApiResponseFactory $apiResponseFactory) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->apiResponseFactory->json([
            'status' => 'OK',
        ], headers: [
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
