<?php

declare(strict_types=1);

namespace Packio\Worker\Infrastructure\Mapper;

use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Domain\Entity\Job;

use function array_map;

final readonly class JobDtoMapper
{
    public function map(Job $job, int $warningCount = 0): JobDto
    {
        return new JobDto(
            id: $job->getIdentifier()->toString(),
            organizationId: $job->organizationId(),
            type: $job->type(),
            payload: $job->payload(),
            idempotencyKey: $job->idempotencyKey(),
            status: $job->status(),
            priority: $job->priority(),
            scheduledAt: $job->scheduledAt()?->format(DATE_ATOM) ?? '',
            startedAt: $job->startedAt()?->format(DATE_ATOM),
            completedAt: $job->completedAt()?->format(DATE_ATOM),
            attemptCount: $job->attemptCount(),
            maxAttempts: $job->maxAttempts(),
            errorMessage: $job->errorMessage(),
            errorDetails: $job->errorDetails(),
            workerId: $job->workerId(),
            createdAt: $job->getCreatedAt()->format(DATE_ATOM),
            durationSeconds: $job->durationSeconds(),
            warningCount: $warningCount,
        );
    }

    /**
     * @param list<Job> $jobs
     *
     * @return list<JobDto>
     */
    public function mapList(array $jobs): array
    {
        return array_map(
            static fn (Job $job): JobDto => new self()->map($job),
            $jobs,
        );
    }

    /**
     * @param list<Job>          $jobs
     * @param array<string, int> $warningCounts
     *
     * @return list<JobDto>
     */
    public function mapListWithWarningCounts(array $jobs, array $warningCounts): array
    {
        return array_map(
            static fn (Job $job): JobDto => new self()->map(
                $job,
                $warningCounts[$job->getIdentifier()->toString()] ?? 0,
            ),
            $jobs,
        );
    }
}
