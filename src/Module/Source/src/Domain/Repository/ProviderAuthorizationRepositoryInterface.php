<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Repository;

use Packio\Source\Domain\Entity\ProviderAuthorization;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

interface ProviderAuthorizationRepositoryInterface
{
    public function save(ProviderAuthorization $providerAuthorization): ProviderAuthorization;

    public function findById(string $id): ?ProviderAuthorization;

    public function findByIdInOrganization(string $id, string $organizationId): ?ProviderAuthorization;

    /**
     * @return list<ProviderAuthorization>
     */
    public function findByOrganization(string $organizationId, ?string $provider = null): array;

    /**
     * @return list<ProviderAuthorization>
     */
    public function findActiveBySourceOAuthAccountId(string $sourceOAuthAccountId, ?string $organizationId = null): array;

    /**
     * @return list<ProviderAuthorization>
     */
    public function findByProviderInstance(string $providerInstanceId, string $organizationId): array;

    /**
     * @return list<ProviderAuthorization>
     */
    public function findLockedByProviderInstance(string $providerInstanceId, string $organizationId): array;

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void;
}
