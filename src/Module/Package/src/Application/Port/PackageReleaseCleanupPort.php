<?php

declare(strict_types=1);

namespace Packio\Package\Application\Port;

interface PackageReleaseCleanupPort
{
    public function deleteReleasesForPackage(string $packageName, string $organizationId): int;
}
