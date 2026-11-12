<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Adapter\Worker;

use Packio\Registry\Application\Exception\ReleaseArchiveFetchFailedException;
use Packio\Registry\Application\Exception\ReleaseArchiveStorageUnavailableException;
use Packio\Registry\Application\Service\ReleaseArchiveStorageService;
use Packio\Registry\Application\Storage\StoredReleaseArchive;
use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Api\Job\JobResult;
use RuntimeException;

use function is_string;

final readonly class StoreReleaseArchiveJobHandler implements JobHandlerInterface
{
    public const string TYPE = 'store.release.archive';

    public function __construct(private ReleaseArchiveStorageService $releaseArchiveStorageService) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
    {
        $organizationId = $payload['organization_id'] ?? null;
        $packageName    = $payload['package_name'] ?? null;
        $version        = $payload['version'] ?? null;
        $reference      = $payload['reference'] ?? null;

        if (! is_string($organizationId) || '' === $organizationId) {
            return JobResult::failure('Organization ID is required.', shouldRetry: false);
        }

        if (! is_string($packageName) || '' === $packageName) {
            return JobResult::failure('Package name is required.', shouldRetry: false);
        }

        if (! is_string($version) || '' === $version) {
            return JobResult::failure('Version is required.', shouldRetry: false);
        }

        $expectedReference = is_string($reference) && '' !== $reference ? $reference : null;

        $jobLogger->info('Storing release archive', [
            'organization_id' => $organizationId,
            'package_name'    => $packageName,
            'version'         => $version,
        ]);

        try {
            $storedReleaseArchive = $this->releaseArchiveStorageService->ensureStoredArchive(
                $organizationId,
                $packageName,
                $version,
                $expectedReference,
            );
        } catch (ReleaseArchiveFetchFailedException|ReleaseArchiveStorageUnavailableException $exception) {
            $jobLogger->warning('Release archive storage fill failed', [
                'organization_id' => $organizationId,
                'package_name'    => $packageName,
                'version'         => $version,
                'error'           => $exception->getMessage(),
            ]);

            return JobResult::failure($exception->getMessage());
        } catch (RuntimeException $exception) {
            $jobLogger->error('Release archive storage fill failed', [
                'organization_id' => $organizationId,
                'package_name'    => $packageName,
                'version'         => $version,
                'error'           => $exception->getMessage(),
            ]);

            return JobResult::failure($exception->getMessage());
        }

        if (! $storedReleaseArchive instanceof StoredReleaseArchive) {
            $jobLogger->info('Release archive storage fill skipped because the release is no longer available', [
                'organization_id' => $organizationId,
                'package_name'    => $packageName,
                'version'         => $version,
                'reference'       => $expectedReference,
                'outcome'         => 'skipped',
            ]);

            return JobResult::success([
                'outcome'      => 'skipped',
                'reason'       => 'release_not_available',
                'package_name' => $packageName,
                'version'      => $version,
                'reference'    => $expectedReference,
            ]);
        }

        $jobLogger->info('Release archive stored', [
            'organization_id' => $organizationId,
            'package_name'    => $packageName,
            'version'         => $version,
            'key'             => $storedReleaseArchive->key,
            'size'            => $storedReleaseArchive->size,
        ]);

        return JobResult::success([
            'package_name' => $packageName,
            'version'      => $version,
            'key'          => $storedReleaseArchive->key,
            'size'         => $storedReleaseArchive->size,
        ]);
    }
}
