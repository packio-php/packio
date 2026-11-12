<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Worker\Api\Dto\JobDto;

final readonly class JobResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(JobDto $jobDto): array
    {
        return [
            'id'               => $jobDto->id,
            'organization_id'  => $jobDto->organizationId,
            'type'             => $jobDto->type,
            'payload'          => $jobDto->payload,
            'idempotency_key'  => $jobDto->idempotencyKey,
            'status'           => $jobDto->status,
            'priority'         => $jobDto->priority,
            'scheduled_at'     => $jobDto->scheduledAt,
            'started_at'       => $jobDto->startedAt,
            'completed_at'     => $jobDto->completedAt,
            'attempt_count'    => $jobDto->attemptCount,
            'max_attempts'     => $jobDto->maxAttempts,
            'error_message'    => $jobDto->errorMessage,
            'error_details'    => $jobDto->errorDetails,
            'worker_id'        => $jobDto->workerId,
            'created_at'       => $jobDto->createdAt,
            'duration_seconds' => $jobDto->durationSeconds,
            'warning_count'    => $jobDto->warningCount,
            'can_retry'        => $jobDto->canRetry(),
        ];
    }
}
