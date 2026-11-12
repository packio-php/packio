<?php

declare(strict_types=1);

namespace Packio\Registry\Api;

interface ArchiveStorageCleanupApiFacadeInterface
{
    /**
     * Schedules deletion of archive objects currently referenced by releases for the package.
     *
     * This is a best-effort cleanup boundary: implementations must not throw when cleanup
     * cannot be scheduled.
     *
     * @return array{discovered: int, scheduled: int, failed: int}
     */
    public function schedulePackageArchiveCleanup(
        string $organizationId,
        string $packageName,
        ?string $deletionRunId = null,
        string $origin = '',
    ): array;
}
