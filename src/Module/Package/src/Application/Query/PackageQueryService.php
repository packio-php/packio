<?php

declare(strict_types=1);

namespace Packio\Package\Application\Query;

use Packio\Package\Domain\Entity\Package;
use Packio\Package\Domain\Repository\PackageRepositoryInterface;

final readonly class PackageQueryService
{
    public function __construct(private PackageRepositoryInterface $packageRepository) {}

    public function countPackages(): int
    {
        return $this->packageRepository->countAll();
    }

    public function countForOrganization(string $organizationId): int
    {
        return $this->packageRepository->countForOrganization($organizationId);
    }

    /**
     * @return list<Package>
     */
    public function allPackages(): array
    {
        return $this->packageRepository->findAll();
    }

    /**
     * @return list<Package>
     */
    public function packagesForOrganization(string $organizationId): array
    {
        return $this->packageRepository->findByOrganization($organizationId);
    }

    public function findPackageInOrganization(string $organizationId, string $name): ?Package
    {
        return $this->packageRepository->findByName($name, $organizationId);
    }
}
