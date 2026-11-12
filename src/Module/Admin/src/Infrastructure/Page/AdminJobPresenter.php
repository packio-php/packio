<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Page;

use Packio\Worker\Api\Dto\JobDto;

use function is_array;
use function is_scalar;

final readonly class AdminJobPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(JobDto $jobDto): array
    {
        return [
            'id'              => $jobDto->id,
            'organizationId'  => $jobDto->organizationId,
            'type'            => $jobDto->type,
            'payload'         => $jobDto->payload,
            'idempotencyKey'  => $jobDto->idempotencyKey,
            'status'          => $jobDto->status,
            'priority'        => $jobDto->priority,
            'scheduledAt'     => $jobDto->scheduledAt,
            'startedAt'       => $jobDto->startedAt,
            'completedAt'     => $jobDto->completedAt,
            'attemptCount'    => $jobDto->attemptCount,
            'maxAttempts'     => $jobDto->maxAttempts,
            'errorMessage'    => $jobDto->errorMessage,
            'workerId'        => $jobDto->workerId,
            'createdAt'       => $jobDto->createdAt,
            'durationSeconds' => $jobDto->durationSeconds,
            'warningCount'    => $jobDto->warningCount,
        ];
    }

    /**
     * @param array<mixed> $payload
     */
    public function deletionRunId(array $payload): ?string
    {
        foreach ($payload as $key => $value) {
            if ('deletion_run_id' === $key && is_scalar($value) && '' !== (string) $value) {
                return (string) $value;
            }

            if (is_array($value)) {
                $nested = $this->deletionRunId($value);
                if (null !== $nested) {
                    return $nested;
                }
            }
        }

        return null;
    }
}
