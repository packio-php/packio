<?php

declare(strict_types=1);

namespace Packio\Worker\Application;

use Cake\Chronos\Chronos;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;

use function in_array;

final readonly class JobRestartService
{
    /** @var list<string> */
    private const array DESTRUCTIVE_LIFECYCLE_TYPES = [
        'organization.delete',
        'source.delete',
    ];

    public function __construct(
        private JobRepositoryInterface $jobRepository,
        private JobLogRepositoryInterface $jobLogRepository,
    ) {}

    public function restartAsAdmin(string $jobId, string $adminUserId): ?Job
    {
        $originalJob = $this->jobRepository->findById($jobId);
        if (! $originalJob instanceof Job) {
            return null;
        }

        // Lifecycle producers own scheduling, generations, and recovery.
        // Copying an old payload here can execute a stale destructive run.
        if (in_array($originalJob->type(), self::DESTRUCTIVE_LIFECYCLE_TYPES, true)) {
            return null;
        }

        $newJob = Job::schedule(
            organizationId: $originalJob->organizationId(),
            type: $originalJob->type(),
            payload: $originalJob->payload(),
            priority: $originalJob->priority(),
            scheduledAt: new Chronos(),
            maxAttempts: $originalJob->maxAttempts(),
        );

        $this->jobRepository->save($newJob);

        $originalJobId = $originalJob->getIdentifier()->toString();
        $newJobId      = $newJob->getIdentifier()->toString();

        $this->jobLogRepository->save(JobLog::record(
            jobId: $newJobId,
            level: JobLog::LEVEL_INFO,
            message: 'Job restarted by admin',
            context: [
                'original_job_id'          => $originalJobId,
                'original_status'          => $originalJob->status(),
                'original_attempt_count'   => $originalJob->attemptCount(),
                'original_max_attempts'    => $originalJob->maxAttempts(),
                'original_idempotency_key' => $originalJob->idempotencyKey(),
                'admin_user_id'            => $adminUserId,
            ],
        ));

        $this->jobLogRepository->save(JobLog::record(
            jobId: $originalJobId,
            level: JobLog::LEVEL_INFO,
            message: 'Admin restart scheduled',
            context: [
                'restarted_job_id' => $newJobId,
                'admin_user_id'    => $adminUserId,
            ],
        ));

        return $newJob;
    }
}
