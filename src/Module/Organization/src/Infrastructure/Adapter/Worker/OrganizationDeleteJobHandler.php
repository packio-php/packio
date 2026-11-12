<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Adapter\Worker;

use Packio\Organization\Application\Command\OrganizationDeletionService;
use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Api\Job\JobResult;
use Throwable;

use function is_string;
use function trim;

final readonly class OrganizationDeleteJobHandler implements JobHandlerInterface
{
    private const string STAGE_SCHEDULE_CLEANUP = 'schedule_cleanup';
    private const string STAGE_FINALIZE         = 'finalize';

    public function __construct(private OrganizationDeletionService $organizationDeletionService) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
    {
        $organizationId    = $payload['organization_id'] ?? null;
        $requestedByUserId = $payload['requested_by_user_id'] ?? null;
        $deletionRunId     = $payload['deletion_run_id'] ?? null;
        $stage             = $payload['stage'] ?? self::STAGE_SCHEDULE_CLEANUP;

        if (! is_string($organizationId) || '' === trim($organizationId)) {
            return JobResult::failure('Organization ID is required.', shouldRetry: false);
        }

        if (! is_string($deletionRunId) || '' === trim($deletionRunId)) {
            $deletionRunId = 'legacy-' . trim($organizationId);
            $jobLogger->warning('Organization deletion payload is missing deletion_run_id; using legacy fallback.', [
                'organization_id' => $organizationId,
                'deletion_run_id' => $deletionRunId,
            ]);
        }

        if (! is_string($stage) || '' === trim($stage)) {
            $stage = self::STAGE_SCHEDULE_CLEANUP;
        }

        try {
            return match (trim($stage)) {
                self::STAGE_SCHEDULE_CLEANUP => $this->scheduleCleanup(
                    trim($organizationId),
                    trim($deletionRunId),
                    is_string($requestedByUserId) ? $requestedByUserId : '',
                    $jobLogger,
                ),
                self::STAGE_FINALIZE         => $this->finalize(trim($organizationId), trim($deletionRunId), $jobLogger),
                default                      => JobResult::failure('Unsupported organization deletion stage.', [
                    'organization_id' => trim($organizationId),
                    'deletion_run_id' => trim($deletionRunId),
                    'stage'           => trim($stage),
                ], shouldRetry: false),
            };
        } catch (Throwable $throwable) {
            $jobLogger->error('Organization deletion stage failed', [
                'organization_id' => trim($organizationId),
                'deletion_run_id' => trim($deletionRunId),
                'stage'           => trim($stage),
                'error'           => $throwable->getMessage(),
            ]);

            return JobResult::failure($throwable->getMessage());
        }
    }

    private function scheduleCleanup(
        string $organizationId,
        string $deletionRunId,
        string $requestedByUserId,
        JobLoggerInterface $jobLogger,
    ): JobResult {
        $jobLogger->info('Scheduling organization cleanup jobs', [
            'organization_id'      => $organizationId,
            'deletion_run_id'      => $deletionRunId,
            'requested_by_user_id' => $requestedByUserId,
        ]);

        $result = $this->organizationDeletionService->scheduleCleanup(
            $organizationId,
            $deletionRunId,
            $requestedByUserId,
        );

        $jobLogger->info('Organization cleanup scheduling completed', $result);

        return JobResult::success($result);
    }

    private function finalize(string $organizationId, string $deletionRunId, JobLoggerInterface $jobLogger): JobResult
    {
        $jobLogger->info('Finalizing organization deletion', [
            'organization_id' => $organizationId,
            'deletion_run_id' => $deletionRunId,
        ]);

        $result = $this->organizationDeletionService->finalize($organizationId, $deletionRunId);

        if (($result['waiting_cleanup'] ?? false) === true) {
            $jobLogger->info('Organization deletion is waiting for cleanup jobs', $result);

            return JobResult::failure('Cleanup jobs are still running.', $result);
        }

        if (($result['blocked'] ?? false) === true) {
            $jobLogger->error('Organization deletion finalization is blocked', $result);

            return JobResult::failure('Organization deletion finalization is blocked.', $result, shouldRetry: false);
        }

        $jobLogger->info('Organization deletion finalized', $result);

        return JobResult::success($result);
    }
}
