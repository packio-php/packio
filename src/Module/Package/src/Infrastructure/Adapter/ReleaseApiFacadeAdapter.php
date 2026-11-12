<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Adapter;

use Packio\Package\Application\Port\PackageReleaseCleanupPort;
use Packio\Package\Application\Port\ReleaseServicePort;
use Packio\Release\Api\ReleaseApiFacadeInterface;

final readonly class ReleaseApiFacadeAdapter implements ReleaseServicePort, PackageReleaseCleanupPort
{
    public function __construct(private ReleaseApiFacadeInterface $releaseApiFacade) {}

    public function incrementDownloadCount(string $packageName, string $version, string $organizationId): bool
    {
        return $this->releaseApiFacade->incrementDownloadCount($packageName, $version, $organizationId);
    }

    public function deleteReleasesForPackage(string $packageName, string $organizationId): int
    {
        return $this->releaseApiFacade->deleteReleasesForPackage($packageName, $organizationId);
    }
}
