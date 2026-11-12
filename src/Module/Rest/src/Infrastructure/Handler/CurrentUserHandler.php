<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use OpenApi\Attributes as OA;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\UserResource;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/me', name: 'api.v1.me')]
#[ApiAuthenticated]
#[ApiScope('read')]
#[OA\Get(
    path: '/api/v1/me',
    operationId: 'currentUser',
    tags: ['User'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Current user',
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/UserResource'),
                ],
                type: 'object',
            ),
        ),
        new OA\Response(response: 401, description: 'Authentication required', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
    ],
)]
final readonly class CurrentUserHandler implements RequestHandlerInterface
{
    public function __construct(private ApiResponseFactory $apiResponseFactory) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = RequestContextAttributes::currentUser($request);
        if (! $user instanceof UserDto) {
            return $this->apiResponseFactory->json([
                'data' => null,
            ], 401);
        }

        return $this->apiResponseFactory->json([
            'data' => UserResource::fromDto($user),
        ]);
    }
}
