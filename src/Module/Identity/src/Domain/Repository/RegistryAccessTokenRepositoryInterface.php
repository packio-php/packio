<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Repository;

use Packio\Identity\Domain\Dto\RegistryAccessTokenRegenerationOutcome;
use Packio\Identity\Domain\Dto\RegistryAccessTokenRegenerationSpec;
use Packio\Identity\Domain\Entity\RegistryAccessToken;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

interface RegistryAccessTokenRepositoryInterface
{
    public function regenerate(
        string $organizationId,
        string $userId,
        string $tokenId,
        RegistryAccessTokenRegenerationSpec $registryAccessTokenRegenerationSpec,
    ): ?RegistryAccessTokenRegenerationOutcome;

    public function save(RegistryAccessToken $registryAccessToken): RegistryAccessToken;

    public function findById(string $id): ?RegistryAccessToken;

    public function findByIdForOwnerInOrganization(string $id, string $ownerUserId, string $organizationId): ?RegistryAccessToken;

    public function findByTokenHash(string $tokenHash): ?RegistryAccessToken;

    /**
     * @return list<RegistryAccessToken>
     */
    public function latestForOrganization(string $organizationId): array;

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void;
}
