<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Adapter;

use Packio\Organization\Application\Port\PackageDeletionSchedulerPort;
use Packio\Package\Api\PackageApiFacadeInterface;

final readonly class PackageDeletionSchedulerAdapter implements PackageDeletionSchedulerPort
{
    public function __construct(private PackageApiFacadeInterface $packageApiFacade) {}

    public function packagesForOrganization(string $organizationId): array
    {
        return $this->packageApiFacade->packagesForOrganization($organizationId);
    }

    public function schedulePackageDelete(string $organizationId, string $packageName, string $deletionRunId): bool
    {
        return $this->packageApiFacade->schedulePackageDelete(
            $organizationId,
            $packageName,
            $deletionRunId,
            'organization.delete',
        );
    }
}
