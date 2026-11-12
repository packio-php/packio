<?php

declare(strict_types=1);

namespace Packio\Package\Application\Port;

interface PackageArchiveCleanupPort
{
    /**
     * @return array{discovered: int, scheduled: int, failed: int}
     */
    public function schedulePackageArchiveCleanup(
        string $organizationId,
        string $packageName,
        ?string $deletionRunId = null,
        string $origin = '',
    ): array;
}
