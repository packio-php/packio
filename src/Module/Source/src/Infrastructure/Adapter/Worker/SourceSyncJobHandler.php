<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter\Worker;

use Packio\Source\Application\Service\SourceSyncExecutionInterface;
use Packio\Source\Application\Service\SourceSyncOutcome;
use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Api\Job\JobResult;

use function is_string;

final readonly class SourceSyncJobHandler implements JobHandlerInterface
{
    public function __construct(private SourceSyncExecutionInterface $sourceSyncExecution) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
    {
        $sourceId       = $payload['source_id'] ?? null;
        $organizationId = $payload['organization_id'] ?? null;

        if (! is_string($sourceId) || '' === $sourceId) {
            return JobResult::failure('Source ID is required.');
        }

        if (! is_string($organizationId) || '' === $organizationId) {
            return JobResult::failure('Organization ID is required.');
        }

        $jobLogger->info('Starting source sync', [
            'source_id'       => $sourceId,
            'organization_id' => $organizationId,
        ]);

        $sourceSyncExecutionResult = $this->sourceSyncExecution->execute($sourceId, $organizationId, new SourceJobLogger($jobLogger));
        $data                      = [
            ...$sourceSyncExecutionResult->data,
            'outcome' => $sourceSyncExecutionResult->outcome->value,
        ];

        return match ($sourceSyncExecutionResult->outcome) {
            SourceSyncOutcome::Synchronized, SourceSyncOutcome::Degraded          => JobResult::success($data),
            SourceSyncOutcome::RetryableFailure                                   => JobResult::failure($sourceSyncExecutionResult->error ?? 'Source sync failed.', $data),
            SourceSyncOutcome::AuthorizationRequired, SourceSyncOutcome::NotFound => JobResult::failure($sourceSyncExecutionResult->error ?? 'Source sync failed.', $data, shouldRetry: false),
        };
    }
}
