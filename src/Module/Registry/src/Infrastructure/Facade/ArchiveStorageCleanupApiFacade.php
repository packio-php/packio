<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Facade;

use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Registry\Application\Service\PackageArchiveCleanupScheduler;

final readonly class ArchiveStorageCleanupApiFacade implements ArchiveStorageCleanupApiFacadeInterface
{
    public function __construct(private PackageArchiveCleanupScheduler $packageArchiveCleanupScheduler) {}

    public function schedulePackageArchiveCleanup(
        string $organizationId,
        string $packageName,
        ?string $deletionRunId = null,
        string $origin = '',
    ): array {
        return $this->packageArchiveCleanupScheduler->schedule($organizationId, $packageName, $deletionRunId, $origin);
    }
}
