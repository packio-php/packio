<?php

declare(strict_types=1);

namespace Packio\Release\Application\Command;

use Packio\Release\Domain\Entity\Release;
use Packio\Release\Domain\Repository\ReleaseRepositoryInterface;
use RuntimeException;

use function trim;

final readonly class ReleaseMaintenanceService
{
    public function __construct(private ReleaseRepositoryInterface $releaseRepository) {}

    public function updateActivity(string $packageName, string $version, bool $active, string $organizationId): ?Release
    {
        $resolvedOrganizationId = trim($organizationId);
        if ('' === $resolvedOrganizationId) {
            throw new RuntimeException('Organization is required to update releases.');
        }

        $entity = $this->releaseRepository->findByPackageAndVersion(
            $packageName,
            $version,
            $resolvedOrganizationId,
        );
        if (! $entity instanceof Release) {
            return null;
        }

        if ($active) {
            $entity->activate();
        } else {
            $entity->deactivate();
        }

        return $this->releaseRepository->save($entity);
    }

    public function deleteReleasesForPackage(string $packageName, string $organizationId): int
    {
        $resolvedOrganizationId = trim($organizationId);
        if ('' === $resolvedOrganizationId) {
            throw new RuntimeException('Organization is required to delete releases.');
        }

        $deleted = 0;

        foreach ($this->releaseRepository->findByPackageName($packageName, $resolvedOrganizationId) as $release) {
            $this->releaseRepository->deleteAndFlush($release);
            ++$deleted;
        }

        return $deleted;
    }
}
