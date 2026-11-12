<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Service;

use Packio\Registry\Application\Port\JobSchedulerPort;
use Packio\Registry\Application\Port\ReleaseStorageMetadataPort;
use Psr\Log\LoggerInterface;
use Throwable;

use function array_chunk;
use function array_keys;
use function count;
use function trim;

final readonly class PackageArchiveCleanupScheduler
{
    public const string JOB_TYPE = 'cleanup.package.archives';

    private const int CHUNK_SIZE = 100;

    public function __construct(
        private ReleaseStorageMetadataPort $releaseStorageMetadataPort,
        private JobSchedulerPort $jobSchedulerPort,
        private LoggerInterface $logger
    ) {}

    /**
     * @return array{discovered: int, scheduled: int, failed: int}
     */
    public function schedule(
        string $organizationId,
        string $packageName,
        ?string $deletionRunId = null,
        string $origin = '',
    ): array {
        $result = [
            'discovered' => 0,
            'scheduled'  => 0,
            'failed'     => 0,
        ];
        $resolvedDeletionRunId = null !== $deletionRunId ? trim($deletionRunId) : '';
        $resolvedOrigin        = trim($origin);

        try {
            $storageKeys = $this->releaseStorageMetadataPort->distStorageKeysForPackage($organizationId, $packageName);
        } catch (Throwable $throwable) {
            $this->logger->warning('Archive cleanup skipped because release metadata could not be loaded.', [
                'organization_id' => $organizationId,
                'package_name'    => $packageName,
                'exception'       => $throwable,
            ]);
            $result['failed'] = 1;

            return $result;
        }

        $keys = [];
        foreach ($storageKeys as $storageKey) {
            $keys[$storageKey] = true;
        }

        $result['discovered'] = count($keys);

        foreach (array_chunk(array_keys($keys), self::CHUNK_SIZE) as $chunk) {
            try {
                $payload = [
                    'organization_id' => $organizationId,
                    'package_name'    => $packageName,
                    'storage_keys'    => $chunk,
                ];

                if ('' !== $resolvedDeletionRunId) {
                    $payload['deletion_run_id'] = $resolvedDeletionRunId;
                }

                if ('' !== $resolvedOrigin) {
                    $payload['origin'] = $resolvedOrigin;
                }

                $this->jobSchedulerPort->enqueue(
                    $organizationId,
                    self::JOB_TYPE,
                    $payload,
                );
                ++$result['scheduled'];
            } catch (Throwable $throwable) {
                ++$result['failed'];
                $this->logger->warning('Archive cleanup job could not be scheduled.', [
                    'organization_id' => $organizationId,
                    'package_name'    => $packageName,
                    'object_count'    => count($chunk),
                    'exception'       => $throwable,
                ]);
            }
        }

        if (0 !== $result['discovered']) {
            $this->logger->info('Archive cleanup jobs scheduled for package.', [
                'organization_id' => $organizationId,
                'package_name'    => $packageName,
                'discovered'      => $result['discovered'],
                'scheduled'       => $result['scheduled'],
                'failed'          => $result['failed'],
            ]);
        }

        return $result;
    }
}
