<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Adapter;

use Packio\Organization\Application\Port\OrganizationDeletionJobStatusPort;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\JobApiFacadeInterface;

final readonly class OrganizationDeletionJobStatusAdapter implements OrganizationDeletionJobStatusPort
{
    /** @var list<string> */
    private const array CLEANUP_JOB_TYPES = [
        'source.delete',
        'package.delete',
        'cleanup.package.archives',
    ];

    /** @var list<string> */
    private const array RUNNING_OR_PENDING_STATUSES = [
        'pending',
        'running',
    ];

    /** @var list<string> */
    private const array FAILED_STATUSES = [
        'failed',
    ];

    public function __construct(private JobApiFacadeInterface $jobApiFacade) {}

    public function runningOrPendingCleanupJobs(string $organizationId, string $deletionRunId): array
    {
        return $this->mapJobs($this->cleanupJobs($organizationId, $deletionRunId, self::RUNNING_OR_PENDING_STATUSES));
    }

    public function failedCleanupJobs(string $organizationId, string $deletionRunId): array
    {
        return $this->mapJobs($this->cleanupJobs($organizationId, $deletionRunId, self::FAILED_STATUSES));
    }

    /**
     * @param list<string> $statuses
     *
     * @return list<JobDto>
     */
    private function cleanupJobs(string $organizationId, string $deletionRunId, array $statuses): array
    {
        return $this->jobApiFacade->findByOrganizationDeletionRun(
            $organizationId,
            $deletionRunId,
            self::CLEANUP_JOB_TYPES,
            $statuses,
        );
    }

    /**
     * @param iterable<JobDto> $jobs
     *
     * @return list<array{id: string, type: string, status: string, error_message: ?string}>
     */
    private function mapJobs(iterable $jobs): array
    {
        $mapped = [];
        foreach ($jobs as $job) {
            $mapped[] = [
                'id'            => $job->id,
                'type'          => $job->type,
                'status'        => $job->status,
                'error_message' => $job->errorMessage,
            ];
        }

        return $mapped;
    }
}
