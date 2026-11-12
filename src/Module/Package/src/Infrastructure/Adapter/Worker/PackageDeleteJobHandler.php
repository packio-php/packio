<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Adapter\Worker;

use Packio\Package\Application\Deletion\PackageDeletionOutcome;
use Packio\Package\Application\Deletion\PackageDeletionScheduler;
use Packio\Package\Application\Deletion\PackageDeletionService;
use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Api\Job\JobResult;

use function is_string;
use function trim;

final readonly class PackageDeleteJobHandler implements JobHandlerInterface
{
    public const string TYPE = PackageDeletionScheduler::JOB_TYPE;

    public function __construct(private PackageDeletionService $packageDeletionService) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
    {
        $organizationId = $payload['organization_id'] ?? null;
        $packageName    = $payload['package_name'] ?? null;
        $deletionRunId  = $payload['deletion_run_id'] ?? null;
        $origin         = $payload['origin'] ?? null;

        if (! is_string($organizationId) || '' === trim($organizationId)) {
            return JobResult::failure('Organization ID is required.', shouldRetry: false);
        }

        if (! is_string($packageName) || '' === trim($packageName)) {
            return JobResult::failure('Package name is required.', shouldRetry: false);
        }

        $resolvedDeletionRunId = is_string($deletionRunId) ? trim($deletionRunId) : '';
        $resolvedOrigin        = is_string($origin) ? trim($origin) : '';

        $jobLogger->info('Starting package deletion', [
            'organization_id' => $organizationId,
            'package_name'    => $packageName,
            'deletion_run_id' => $resolvedDeletionRunId,
            'origin'          => $resolvedOrigin,
        ]);

        $packageDeletionResult = $this->packageDeletionService->delete(
            $organizationId,
            $packageName,
            $resolvedDeletionRunId,
            $resolvedOrigin,
        );

        switch ($packageDeletionResult->outcome) {
            case PackageDeletionOutcome::Deleted:
            case PackageDeletionOutcome::AlreadyDeleted:
                $jobLogger->info($packageDeletionResult->message, $packageDeletionResult->data);

                return JobResult::success($packageDeletionResult->data);

            case PackageDeletionOutcome::RetryableFailure:
            case PackageDeletionOutcome::Blocked:
                $jobLogger->warning($packageDeletionResult->message, $packageDeletionResult->data);

                return JobResult::failure($packageDeletionResult->message, $packageDeletionResult->data);
        }
    }
}
