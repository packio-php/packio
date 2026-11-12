<?php

declare(strict_types=1);

namespace Packio\Package\Application\Command;

use Packio\Package\Domain\Entity\Package;
use Packio\Package\Domain\Repository\PackageRepositoryInterface;
use RuntimeException;

use function array_filter;
use function array_map;
use function array_values;
use function trim;

final readonly class PackageMaintenanceService
{
    public function __construct(private PackageRepositoryInterface $packageRepository) {}

    public function deletePackage(string $organizationId, string $name): bool
    {
        $entity = $this->packageRepository->findByName($name, $organizationId);
        if (! $entity instanceof Package) {
            return false;
        }

        $this->packageRepository->deleteAndFlush($entity);

        return true;
    }

    public function packageExists(string $organizationId, string $name): bool
    {
        return $this->packageRepository->findByName($name, $organizationId) instanceof Package;
    }

    /**
     * @return list<Package>
     */
    public function updateVisibilityBySourceId(string $sourceId, string $visibility, string $organizationId): array
    {
        $resolvedOrganizationId = trim($organizationId);
        if ('' === $resolvedOrganizationId) {
            throw new RuntimeException('Organization is required to update package visibility.');
        }

        return array_values(
            array_map(
                fn (Package $package): Package => $this->packageRepository->save(
                    $this->updatePackageVisibility($package, $visibility),
                ),
                array_filter(
                    $this->packageRepository->findByOrganization($resolvedOrganizationId),
                    static fn (Package $package): bool => $package->sourceId() === $sourceId,
                ),
            ),
        );
    }

    /**
     * @return list<Package>
     */
    public function updateActivityBySourceId(string $sourceId, bool $active, string $organizationId): array
    {
        $resolvedOrganizationId = trim($organizationId);
        if ('' === $resolvedOrganizationId) {
            throw new RuntimeException('Organization is required to update package activity.');
        }

        return array_values(
            array_map(
                fn (Package $package): Package => $this->packageRepository->save(
                    $this->updatePackageActivity($package, $active),
                ),
                array_filter(
                    $this->packageRepository->findByOrganization($resolvedOrganizationId),
                    static fn (Package $package): bool => $package->sourceId() === $sourceId,
                ),
            ),
        );
    }

    private function updatePackageVisibility(Package $package, string $visibility): Package
    {
        $package->updateMetadata(
            $package->type(),
            $package->description(),
            $visibility,
            $package->sourceId(),
            $package->sourceType(),
            $package->sourceUrl(),
        );

        return $package;
    }

    private function updatePackageActivity(Package $package, bool $active): Package
    {
        if ($active) {
            $package->activate();
        } else {
            $package->deactivate();
        }

        return $package;
    }
}
