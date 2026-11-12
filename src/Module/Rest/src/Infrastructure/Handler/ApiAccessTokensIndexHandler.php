<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use OpenApi\Attributes as OA;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Resource\ApiAccessTokenResource;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/user/api-tokens', name: 'api.v1.user.api-tokens.index')]
#[ApiAuthenticated]
#[ApiScope('read')]
#[OA\Get(
    path: '/api/v1/user/api-tokens',
    operationId: 'listApiTokens',
    tags: ['User API Tokens'],
    responses: [
        new OA\Response(response: 200, description: 'API token list'),
        new OA\Response(response: 401, description: 'Authentication required', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
    ],
)]
final readonly class ApiAccessTokensIndexHandler implements RequestHandlerInterface
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

        $tokens = [];
        foreach ($this->identityApiFacade->apiAccessTokensForUser($user->id) as $apiAccessTokenDto) {
            $tokens[] = ApiAccessTokenResource::fromDto($apiAccessTokenDto);
        }

        return $this->apiResponseFactory->json([
            'data' => $tokens,
        ]);
    }
}
