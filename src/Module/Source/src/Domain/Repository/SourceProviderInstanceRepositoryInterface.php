<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Repository;

use Packio\Source\Domain\Entity\SourceProviderInstance;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

interface SourceProviderInstanceRepositoryInterface
{
    public function save(SourceProviderInstance $sourceProviderInstance): SourceProviderInstance;

    public function findById(string $id): ?SourceProviderInstance;

    public function findByIdInOrganization(string $id, string $organizationId): ?SourceProviderInstance;

    public function findActiveByIdInOrganization(string $id, string $organizationId): ?SourceProviderInstance;

    public function findByOrganizationProviderAndBaseUrl(
        string $organizationId,
        string $providerType,
        string $baseUrl
    ): ?SourceProviderInstance;

    /**
     * @return list<SourceProviderInstance>
     */
    public function findByOrganization(string $organizationId, ?string $providerType = null): array;

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void;
}
