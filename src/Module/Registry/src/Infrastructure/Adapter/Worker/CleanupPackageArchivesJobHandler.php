<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Adapter\Worker;

use Packio\Registry\Application\Service\PackageArchiveCleanupScheduler;
use Packio\Registry\Application\Storage\PackageArchiveStorageInterface;
use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Api\Job\JobResult;
use Throwable;

use function array_filter;
use function array_values;
use function count;
use function is_array;
use function is_string;

final readonly class CleanupPackageArchivesJobHandler implements JobHandlerInterface
{
    public const string TYPE = PackageArchiveCleanupScheduler::JOB_TYPE;

    public function __construct(private PackageArchiveStorageInterface $packageArchiveStorage) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
    {
        $organizationId = $payload['organization_id'] ?? null;
        $packageName    = $payload['package_name'] ?? null;
        $storageKeys    = $payload['storage_keys'] ?? null;

        if (! is_string($organizationId) || '' === $organizationId) {
            return JobResult::failure('Organization ID is required.', shouldRetry: false);
        }

        if (! is_string($packageName) || '' === $packageName) {
            return JobResult::failure('Package name is required.', shouldRetry: false);
        }

        if (! is_array($storageKeys)) {
            return JobResult::failure('Storage keys are required.', shouldRetry: false);
        }

        $keys = array_values(array_filter($storageKeys, static fn (mixed $key): bool => is_string($key) && '' !== $key));
        if ([] === $keys) {
            return JobResult::success([
                'deleted' => 0,
                'failed'  => 0,
            ]);
        }

        $deleted = 0;
        $failed  = 0;

        $jobLogger->info('Starting archive cleanup.', [
            'organization_id' => $organizationId,
            'package_name'    => $packageName,
            'object_count'    => count($keys),
        ]);

        foreach ($keys as $key) {
            try {
                $this->packageArchiveStorage->delete($key);
                ++$deleted;
            } catch (Throwable $throwable) {
                ++$failed;
                $jobLogger->warning('Archive object cleanup failed.', [
                    'organization_id' => $organizationId,
                    'package_name'    => $packageName,
                    'storage_key'     => $key,
                    'error'           => $throwable->getMessage(),
                ]);
            }
        }

        $jobLogger->info('Archive cleanup finished.', [
            'organization_id' => $organizationId,
            'package_name'    => $packageName,
            'deleted'         => $deleted,
            'failed'          => $failed,
        ]);

        if (0 < $failed) {
            return JobResult::failure('Archive object cleanup failed.', [
                'deleted' => $deleted,
                'failed'  => $failed,
            ]);
        }

        return JobResult::success([
            'deleted' => $deleted,
            'failed'  => $failed,
        ]);
    }
}
