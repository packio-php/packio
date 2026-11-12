<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Port;

interface PackageDeletionSchedulerPort
{
    /** @return list<object{name: string, organizationId: string, sourceId: string}> */
    public function packagesForOrganization(string $organizationId): array;

    public function schedulePackageDelete(string $organizationId, string $packageName, string $deletionRunId): bool;
}
