<?php

declare(strict_types=1);

namespace Packio\Package\Application\Listener;

use Packio\Package\Api\Event\PackageDownloaded;
use Packio\Package\Application\Port\JobSchedulerPort;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class IncrementDownloadCountListener
{
    public function __construct(private JobSchedulerPort $jobSchedulerPort, private ?LoggerInterface $logger = null) {}

    public function __invoke(PackageDownloaded $packageDownloaded): void
    {
        try {
            $this->jobSchedulerPort->enqueue(
                $packageDownloaded->organizationId,
                'increment.download.count',
                [
                    'organization_id' => $packageDownloaded->organizationId,
                    'package_name'    => $packageDownloaded->packageName,
                    'version'         => $packageDownloaded->version,
                ],
            );
        } catch (Throwable $e) {
            $this->logger?->error('Failed to enqueue download count job', [
                'organization_id' => $packageDownloaded->organizationId,
                'package'         => $packageDownloaded->packageName,
                'version'         => $packageDownloaded->version,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
