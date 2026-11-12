<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Repository;

use Packio\Identity\Domain\Dto\ApiAccessTokenRegenerationOutcome;
use Packio\Identity\Domain\Dto\ApiAccessTokenRegenerationSpec;
use Packio\Identity\Domain\Entity\ApiAccessToken;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

interface ApiAccessTokenRepositoryInterface
{
    public function save(ApiAccessToken $apiAccessToken): ApiAccessToken;

    public function findByTokenHash(string $tokenHash): ?ApiAccessToken;

    public function findByIdForUser(string $id, string $userId): ?ApiAccessToken;

    /**
     * Loads and revalidates the owned, unrevoked, unexpired token inside a
     * transaction (after an advisory lock and with SELECT ... FOR UPDATE),
     * issues the replacement from the given spec and revokes the previous
     * token in the same transaction boundary. Returns null when the token is
     * missing or no longer active.
     */
    public function regenerate(
        string $userId,
        string $tokenId,
        ApiAccessTokenRegenerationSpec $apiAccessTokenRegenerationSpec,
    ): ?ApiAccessTokenRegenerationOutcome;

    /**
     * @return list<ApiAccessToken>
     */
    public function latestForUser(string $userId): array;

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void;
}
