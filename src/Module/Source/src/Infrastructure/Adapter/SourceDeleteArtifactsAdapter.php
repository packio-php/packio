<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter;

use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Source\Application\Dto\SourceDeleteArtifactsResult;
use Packio\Source\Application\Port\SourceDeleteArtifactsPort;
use Packio\Source\Application\Service\SourceDeletionExecutionLeaseInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use RuntimeException;
use Throwable;

use function array_filter;
use function array_values;
use function count;

final readonly class SourceDeleteArtifactsAdapter implements SourceDeleteArtifactsPort
{
    public function __construct(
        private PackageApiFacadeInterface $packageApiFacade,
        private ArchiveStorageCleanupApiFacadeInterface $archiveStorageCleanupApiFacade,
        private ReleaseApiFacadeInterface $releaseApiFacade,
        private WebhookApiFacadeInterface $webhookApiFacade,
        private SourceDeletionExecutionLeaseInterface $sourceDeletionExecutionLease,
    ) {}

    public function deleteRelatedArtifacts(
        string $sourceId,
        string $organizationId,
        ?string $deletionRunId = null,
        string $origin = '',
    ): SourceDeleteArtifactsResult {
        if (null === $deletionRunId || '' === $deletionRunId) {
            throw new RuntimeException('Source deletion lifecycle generation is required.');
        }

        $packages = array_values(array_filter(
            $this->packageApiFacade->packagesForOrganization($organizationId),
            static fn (PackageDto $packageDto): bool => $packageDto->sourceId === $sourceId,
        ));

        $packagesDeleted         = 0;
        $releasesDeleted         = 0;
        $archiveCleanupScheduled = 0;
        $archiveCleanupFailed    = 0;
        $archiveCleanupWarnings  = [];

        foreach ($packages as $package) {
            $this->sourceDeletionExecutionLease->assertHeld($sourceId, $organizationId, $deletionRunId);

            try {
                $cleanup = $this->archiveStorageCleanupApiFacade->schedulePackageArchiveCleanup(
                    $organizationId,
                    $package->name,
                    $deletionRunId,
                    $origin,
                );
                $archiveCleanupScheduled += $cleanup['scheduled'];
                $archiveCleanupFailed += $cleanup['failed'];
                if (0 < $cleanup['failed']) {
                    $archiveCleanupWarnings[] = [
                        'package_name' => $package->name,
                        'error'        => 'Archive cleanup could not be fully scheduled.',
                    ];

                    throw new RuntimeException('Archive cleanup could not be fully scheduled for package ' . $package->name . '.');
                }
            } catch (Throwable $throwable) {
                ++$archiveCleanupFailed;
                $archiveCleanupWarnings[] = [
                    'package_name' => $package->name,
                    'error'        => $throwable->getMessage(),
                ];

                throw $throwable;
            }

            $this->sourceDeletionExecutionLease->assertHeld($sourceId, $organizationId, $deletionRunId);
            $releasesDeleted += $this->releaseApiFacade->deleteReleasesForPackage(
                $package->name,
                $organizationId,
            );

            $this->sourceDeletionExecutionLease->assertHeld($sourceId, $organizationId, $deletionRunId);
            if ($this->packageApiFacade->deletePackage($organizationId, $package->name)) {
                ++$packagesDeleted;
            }
        }

        $this->sourceDeletionExecutionLease->assertHeld($sourceId, $organizationId, $deletionRunId);

        return new SourceDeleteArtifactsResult(
            packagesFound: count($packages),
            packagesDeleted: $packagesDeleted,
            releasesDeleted: $releasesDeleted,
            archiveCleanupScheduled: $archiveCleanupScheduled,
            archiveCleanupFailed: $archiveCleanupFailed,
            webhookEventsDeleted: $this->webhookApiFacade->deleteEventsForSource($sourceId),
            archiveCleanupWarnings: $archiveCleanupWarnings,
        );
    }
}
