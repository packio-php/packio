<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Repository;

use Packio\Source\Domain\Dto\SourceOAuthRefreshCriteria;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

interface SourceOAuthAccountRepositoryInterface
{
    public function findById(string $id): ?SourceOAuthAccount;

    public function findByCredentialIdentity(
        string $userId,
        string $provider,
        string $providerUserId,
        ?string $providerInstanceId
    ): ?SourceOAuthAccount;

    public function findActiveByCredentialIdentity(
        string $userId,
        string $provider,
        string $providerUserId,
        ?string $providerInstanceId
    ): ?SourceOAuthAccount;

    /**
     * @return list<SourceOAuthAccount>
     */
    public function findActiveAccountsByCredentialIdentity(
        string $userId,
        string $provider,
        string $providerUserId,
        ?string $providerInstanceId
    ): array;

    /**
     * @return list<SourceOAuthAccount>
     */
    public function findByProviderInstance(string $providerInstanceId): array;

    /**
     * @return list<SourceOAuthAccount>
     */
    public function findLockedByProviderInstance(string $providerInstanceId): array;

    /**
     * @return list<SourceOAuthAccount>
     */
    public function findRefreshCandidates(SourceOAuthRefreshCriteria $sourceOAuthRefreshCriteria): array;

    public function save(SourceOAuthAccount $sourceOAuthAccount): void;

    /**
     * @param callable(SourceOAuthAccount): void $updateExisting
     */
    public function saveOrUpdateByCredentialKey(
        SourceOAuthAccount $sourceOAuthAccount,
        callable $updateExisting,
        ?string $organizationId = null,
    ): SourceOAuthAccount;

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void;
}
