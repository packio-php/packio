<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use OpenApi\Attributes as OA;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Rest\Attribute\ApiAudited;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Delete;

#[Delete('/api/v1/user/api-tokens/:tokenId', name: 'api.v1.user.api-tokens.delete')]
#[ApiAuthenticated]
#[ApiScope('write')]
#[ApiAudited]
#[RateLimit(key: 'api_destructive', by: ['ip', 'user'], response: 'json', message: 'Too many destructive API requests. Please try again later.')]
#[OA\Delete(
    path: '/api/v1/user/api-tokens/{tokenId}',
    operationId: 'revokeApiToken',
    tags: ['User API Tokens'],
    parameters: [
        new OA\Parameter(
            name: 'tokenId',
            description: 'API token UUID.',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid'),
        ),
    ],
    responses: [
        new OA\Response(response: 204, description: 'API token revoked'),
        new OA\Response(response: 401, description: 'Authentication required', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        new OA\Response(response: 404, description: 'Token not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
    ],
)]
final readonly class ApiAccessTokensDeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = RequestContextAttributes::currentUser($request);
        if (! $user instanceof UserDto) {
            return $this->apiErrorResponseFactory->unauthorized();
        }

        $tokenId = ApiRouteResourceResolver::tokenId($request);
        if ('' === $tokenId) {
            return $this->apiErrorResponseFactory->notFound('API token not found.');
        }

        if (! $this->identityApiFacade->revokeApiAccessTokenForUser($user->id, $tokenId)) {
            return $this->apiErrorResponseFactory->notFound('API token not found.');
        }

        return $this->apiResponseFactory->empty();
    }
}
