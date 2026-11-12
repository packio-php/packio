<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Port;

use Packio\Registry\Application\Dto\PackageData;

interface PackageQueryPort
{
    /** @return PackageData[] */
    public function allPackages(string $organizationId): array;

    public function findPackageInOrganization(string $organizationId, string $name): ?PackageData;
}
