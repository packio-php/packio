<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Port;

use Packio\Registry\Application\Dto\ReleaseData;

interface ReleaseQueryPort
{
    /** @return ReleaseData[] */
    public function releasesForPackage(string $organizationId, string $packageName): array;

    public function findRelease(string $organizationId, string $packageName, string $version): ?ReleaseData;

    /** @return array<string, string> package name => highest active version */
    public function latestActiveVersions(string $organizationId): array;
}
