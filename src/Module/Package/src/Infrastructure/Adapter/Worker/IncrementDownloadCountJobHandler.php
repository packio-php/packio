<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Adapter\Worker;

use Packio\Package\Application\Command\PackageCommandService;
use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Api\Job\JobResult;

use function is_string;

final readonly class IncrementDownloadCountJobHandler implements JobHandlerInterface
{
    public function __construct(private PackageCommandService $packageCommandService) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
    {
        $packageName    = $payload['package_name'] ?? null;
        $organizationId = $payload['organization_id'] ?? null;
        $version        = $payload['version'] ?? null;

        if (! is_string($packageName) || '' === $packageName) {
            return JobResult::failure('Package name is required.');
        }

        if (! is_string($organizationId) || '' === $organizationId) {
            return JobResult::failure('Organization ID is required.');
        }

        if (! is_string($version) || '' === $version) {
            return JobResult::failure('Version is required.');
        }

        $jobLogger->info('Incrementing download count', [
            'package'         => $packageName,
            'version'         => $version,
            'organization_id' => $organizationId,
        ]);

        $success = $this->packageCommandService->incrementDownloadCount(
            $organizationId,
            $packageName,
            $version,
        );

        if (! $success) {
            $jobLogger->warning('Package not found for download count increment', [
                'package'         => $packageName,
                'organization_id' => $organizationId,
            ]);

            return JobResult::failure('Package not found.', shouldRetry: false);
        }

        $jobLogger->info('Download count incremented', [
            'package' => $packageName,
            'version' => $version,
        ]);

        return JobResult::success([
            'package' => $packageName,
            'version' => $version,
        ]);
    }
}
