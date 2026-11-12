<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter\Worker;

use Packio\Source\Application\Exception\SourceDeletionExecutionLeaseLostException;
use Packio\Source\Application\Service\SourceDeletionExecutionInterface;
use Packio\Source\Application\Service\SourceDeletionOutcome;
use Packio\Worker\Api\Exception\WorkerRestartRequiredException;
use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Api\Job\JobResult;

use function is_string;

final readonly class SourceDeleteJobHandler implements JobHandlerInterface
{
    public function __construct(private SourceDeletionExecutionInterface $sourceDeletionExecution) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
    {
        $sourceId       = $payload['source_id'] ?? null;
        $organizationId = $payload['organization_id'] ?? null;
        $deletionRunId  = $payload['deletion_run_id'] ?? null;
        $origin         = $payload['origin'] ?? null;

        if (! is_string($sourceId) || '' === $sourceId) {
            return JobResult::failure('Source ID is required.', shouldRetry: false);
        }

        if (! is_string($organizationId) || '' === $organizationId) {
            return JobResult::failure('Organization ID is required.', shouldRetry: false);
        }

        try {
            $sourceDeletionExecutionResult = $this->sourceDeletionExecution->execute(
                $sourceId,
                $organizationId,
                is_string($deletionRunId) ? $deletionRunId : '',
                is_string($origin) ? $origin : '',
                new SourceJobLogger($jobLogger),
            );
        } catch (SourceDeletionExecutionLeaseLostException $e) {
            throw new WorkerRestartRequiredException($e->getMessage(), 0, $e);
        }

        $data = [
            ...$sourceDeletionExecutionResult->data,
            'outcome' => $sourceDeletionExecutionResult->outcome->value,
        ];

        return match ($sourceDeletionExecutionResult->outcome) {
            SourceDeletionOutcome::Deleted, SourceDeletionOutcome::AlreadyDeleted, SourceDeletionOutcome::StaleLease => JobResult::success($data),
            SourceDeletionOutcome::Blocked, SourceDeletionOutcome::RetryableFailure                                  => JobResult::failure($sourceDeletionExecutionResult->error ?? 'Source deletion failed.', $data),
        };
    }
}
