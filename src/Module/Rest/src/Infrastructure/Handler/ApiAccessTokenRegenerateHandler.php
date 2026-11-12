<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Resource\ApiAccessTokenResource;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Identity\Api\Dto\IssuedApiAccessTokenDto;
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
use Sirix\Mezzio\Routing\Attributes\Attribute\Put;

#[Put('/api/v1/user/api-tokens/:tokenId/regenerate', name: 'api.v1.user.api-tokens.regenerate')]
#[ApiAuthenticated]
#[ApiScope('write')]
#[ApiAudited]
#[RateLimit(key: 'api_token_create', by: ['ip', 'user'], response: 'json', message: 'Too many API token requests. Please try again later.')]
final readonly class ApiAccessTokenRegenerateHandler implements RequestHandlerInterface
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

        $apiAccessTokenRegenerationDto = $this->identityApiFacade->regenerateApiAccessTokenForUser($user->id, $tokenId);

        if ($apiAccessTokenRegenerationDto->isPersistenceFailed()) {
            return $this->apiErrorResponseFactory->error(
                'api_token_regenerate_failed',
                'API token could not be regenerated.',
                409,
            );
        }

        if (! $apiAccessTokenRegenerationDto->success || ! $apiAccessTokenRegenerationDto->issued instanceof IssuedApiAccessTokenDto || null === $apiAccessTokenRegenerationDto->revokedId) {
            return $this->apiErrorResponseFactory->notFound('API token not found.');
        }

        return $this->apiResponseFactory->json([
            'data'        => ApiAccessTokenResource::fromDto($apiAccessTokenRegenerationDto->issued->token),
            'plain_token' => $apiAccessTokenRegenerationDto->issued->plainTextToken,
            'revoked_id'  => $apiAccessTokenRegenerationDto->revokedId,
        ], 201);
    }
}
