<?php

declare(strict_types=1);

namespace Packio\Package\Application\Port;

interface ReleaseServicePort
{
    public function incrementDownloadCount(string $packageName, string $version, string $organizationId): bool;
}
