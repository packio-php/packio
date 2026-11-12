<?php

declare(strict_types=1);

namespace Packio\Package\Domain\Repository;

use Packio\Package\Domain\Entity\Package;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

interface PackageRepositoryInterface
{
    public function countAll(): int;

    public function countForOrganization(string $organizationId): int;

    public function save(Package $package): Package;

    public function upsertByNaturalKey(Package $package): Package;

    public function incrementDownloadCount(string $organizationId, string $name): bool;

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void;

    public function findByName(string $name, string $organizationId): ?Package;

    /**
     * @return list<Package>
     */
    public function findByOrganization(string $organizationId): array;

    /**
     * @return list<Package>
     */
    public function findAll(): array;
}
