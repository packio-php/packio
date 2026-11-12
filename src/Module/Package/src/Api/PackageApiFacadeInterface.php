<?php

declare(strict_types=1);

namespace Packio\Package\Api;

use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\Exception\PackageDeletionFailedException;
use Packio\Shared\Domain\Organization\ActorContext;

interface PackageApiFacadeInterface
{
    public function countPackages(): int;

    public function countForOrganization(string $organizationId): int;

    public function upsert(PackageDto $packageDto): PackageDto;

    /**
     * @return list<PackageDto>
     */
    public function allPackages(): array;

    /**
     * @return list<PackageDto>
     */
    public function packagesForOrganization(string $organizationId): array;

    /** @phpstan-impure */
    public function findPackageInOrganization(string $organizationId, string $name): ?PackageDto;

    public function updateReadmeHtml(string $organizationId, string $name, ?string $readmeHtml): ?PackageDto;

    /**
     * @throws PackageDeletionFailedException when archive cleanup cannot be scheduled or deletion fails
     */
    public function deletePackageInContext(ActorContext $actorContext, string $name): bool;

    public function deletePackage(string $organizationId, string $name): bool;

    public function schedulePackageDelete(
        string $organizationId,
        string $name,
        ?string $deletionRunId = null,
        string $origin = '',
    ): bool;

    /**
     * @return list<PackageDto>
     */
    public function updateVisibilityBySourceIdInContext(ActorContext $actorContext, string $sourceId, string $visibility): array;

    /**
     * @return list<PackageDto>
     */
    public function updateActivityBySourceIdInContext(ActorContext $actorContext, string $sourceId, bool $active): array;
}
