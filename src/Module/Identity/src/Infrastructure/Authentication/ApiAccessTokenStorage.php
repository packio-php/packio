<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Authentication;

use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Identity\Application\Query\ApiAccessTokenQueryService;
use Packio\Identity\Application\Result\ApiAccessTokenResult;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Exception\StorageException;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

/**
 * Authentication-only adapter. API token issuance and revocation are owned by Identity use cases.
 *
 * Storage resolves only a valid, active credential. Its use is recorded by the Identity actor
 * provider after that provider has resolved the token owner into an authenticated actor.
 */
final readonly class ApiAccessTokenStorage implements TokenStorageInterface
{
    public function __construct(
        private ApiAccessTokenQueryService $apiAccessTokenQueryService,
        private ObjectMapperInterface $objectMapper,
    ) {}

    public function create(array $payload, ?int $expiresAt = null, ?ServerRequestInterface $serverRequest = null): TokenInterface
    {
        throw new StorageException('API access token storage is authentication-only and cannot create tokens.');
    }

    public function load(string $id, ?ServerRequestInterface $serverRequest = null): ?TokenInterface
    {
        $token = $this->apiAccessTokenQueryService->authenticate($id);

        return $token instanceof ApiAccessTokenResult
            ? new ApiAccessToken($this->objectMapper->map($token, ApiAccessTokenDto::class))
            : null;
    }

    public function delete(TokenInterface $token, ?ServerRequestInterface $serverRequest = null): void
    {
        throw new StorageException('API access token storage is authentication-only and cannot delete tokens.');
    }
}
