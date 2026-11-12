<?php

declare(strict_types=1);

namespace Packio\Package\Application\Deletion;

use Packio\Package\Application\Command\PackageMaintenanceService;
use Packio\Package\Application\Port\PackageArchiveCleanupPort;
use Packio\Package\Application\Port\PackageReleaseCleanupPort;
use Throwable;

final readonly class PackageDeletionService
{
    public function __construct(
        private PackageMaintenanceService $packageMaintenanceService,
        private PackageArchiveCleanupPort $packageArchiveCleanupPort,
        private PackageReleaseCleanupPort $packageReleaseCleanupPort,
    ) {}

    public function delete(
        string $organizationId,
        string $packageName,
        string $deletionRunId,
        string $origin = '',
    ): PackageDeletionResult {
        $data = [
            'organization_id' => $organizationId,
            'package_name'    => $packageName,
            'deletion_run_id' => $deletionRunId,
        ];

        try {
            $exists = $this->packageMaintenanceService->packageExists($organizationId, $packageName);
        } catch (Throwable) {
            return new PackageDeletionResult(
                PackageDeletionOutcome::RetryableFailure,
                $data,
                'Package existence could not be checked.',
            );
        }

        if (! $exists) {
            return new PackageDeletionResult(
                PackageDeletionOutcome::AlreadyDeleted,
                $data + [
                    'package_found'   => false,
                    'deleted'         => false,
                    'already_deleted' => true,
                ],
                'Package already deleted',
            );
        }

        $data += [
            'package_found' => true,
            'deleted'       => false,
        ];

        // Scheduling must complete before the metadata needed to discover archives is removed.
        try {
            $archiveCleanup = $this->packageArchiveCleanupPort->schedulePackageArchiveCleanup(
                $organizationId,
                $packageName,
                '' !== $deletionRunId ? $deletionRunId : null,
                $origin,
            );
        } catch (Throwable) {
            return new PackageDeletionResult(
                PackageDeletionOutcome::Blocked,
                $data,
                'Archive cleanup could not be scheduled.',
            );
        }

        $data += [
            'archive_cleanup_discovered' => $archiveCleanup['discovered'],
            'archive_cleanup_scheduled'  => $archiveCleanup['scheduled'],
            'archive_cleanup_failed'     => $archiveCleanup['failed'],
        ];

        if (0 < $archiveCleanup['failed']) {
            return new PackageDeletionResult(
                PackageDeletionOutcome::Blocked,
                $data,
                'Archive cleanup could not be fully scheduled.',
            );
        }

        // Retries rediscover remaining releases; cleanup jobs tolerate duplicate storage keys.
        try {
            $data['releases_deleted'] = $this->packageReleaseCleanupPort->deleteReleasesForPackage(
                $packageName,
                $organizationId,
            );
        } catch (Throwable) {
            return new PackageDeletionResult(
                PackageDeletionOutcome::RetryableFailure,
                $data,
                'Package releases could not be deleted.',
            );
        }

        try {
            $deleted = $this->packageMaintenanceService->deletePackage($organizationId, $packageName);
        } catch (Throwable) {
            $deleted = false;
        }

        if (! $deleted) {
            return new PackageDeletionResult(
                PackageDeletionOutcome::RetryableFailure,
                $data,
                'Package could not be deleted.',
            );
        }

        $data['deleted'] = true;

        return new PackageDeletionResult(PackageDeletionOutcome::Deleted, $data, 'Package deletion completed');
    }
}
