<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Adapter;

use Packio\Package\Application\Port\PackageArchiveCleanupPort;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;

final readonly class RegistryArchiveCleanupAdapter implements PackageArchiveCleanupPort
{
    public function __construct(private ArchiveStorageCleanupApiFacadeInterface $archiveStorageCleanupApiFacade) {}

    public function schedulePackageArchiveCleanup(
        string $organizationId,
        string $packageName,
        ?string $deletionRunId = null,
        string $origin = '',
    ): array {
        return $this->archiveStorageCleanupApiFacade->schedulePackageArchiveCleanup(
            $organizationId,
            $packageName,
            $deletionRunId,
            $origin,
        );
    }
}
