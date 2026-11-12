<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Query;

use Cake\Chronos\Chronos;
use Packio\Identity\Application\Result\ApiAccessTokenResult;
use Packio\Identity\Application\Security\ApiAccessTokenHasher;
use Packio\Identity\Domain\Entity\ApiAccessToken;
use Packio\Identity\Domain\Repository\ApiAccessTokenRepositoryInterface;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function array_map;
use function trim;

final readonly class ApiAccessTokenQueryService
{
    public function __construct(
        private ApiAccessTokenRepositoryInterface $apiAccessTokenRepository,
        private ApiAccessTokenHasher $apiAccessTokenHasher,
        private ObjectMapperInterface $objectMapper,
    ) {}

    /**
     * Resolves an API credential without loading its user or recording an authenticated use.
     * The authentication package's actor provider resolves the user separately and records use
     * only after it has produced an actor for the token owner.
     */
    public function authenticate(?string $plainTextToken): ?ApiAccessTokenResult
    {
        $token = trim((string) $plainTextToken);
        if ('' === $token) {
            return null;
        }

        $entity = $this->apiAccessTokenRepository->findByTokenHash($this->apiAccessTokenHasher->hash($token));
        if (! $entity instanceof ApiAccessToken) {
            return null;
        }

        $now = new Chronos();
        if ($entity->isRevoked() || $entity->isExpired($now)) {
            return null;
        }

        return $this->objectMapper->map($entity, ApiAccessTokenResult::class);
    }

    /**
     * Records use after an API token's owner was resolved into an authenticated actor.
     */
    public function recordSuccessfulAuthentication(string $tokenId, string $userId): bool
    {
        $entity = $this->apiAccessTokenRepository->findByIdForUser(trim($tokenId), trim($userId));
        if (! $entity instanceof ApiAccessToken) {
            return false;
        }

        $now = new Chronos();
        if ($entity->isRevoked() || $entity->isExpired($now)) {
            return false;
        }

        $entity->markUsed($now);
        $this->apiAccessTokenRepository->save($entity);

        return true;
    }

    /**
     * @return list<ApiAccessTokenResult>
     */
    public function tokensForUser(string $userId): array
    {
        return array_map(
            fn (ApiAccessToken $apiAccessToken): ApiAccessTokenResult => $this->objectMapper->map(
                $apiAccessToken,
                ApiAccessTokenResult::class,
            ),
            $this->apiAccessTokenRepository->latestForUser(trim($userId)),
        );
    }
}
