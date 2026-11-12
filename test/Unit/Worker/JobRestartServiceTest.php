<?php

declare(strict_types=1);

namespace PackioTest\Unit\Worker;

use Cake\Chronos\Chronos;
use Error;
use LogicException;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Worker\Api\Dto\JobFilterCriteria;
use Packio\Worker\Application\JobRestartService;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

use function array_filter;
use function array_values;
use function count;

#[CoversClass(JobRestartService::class)]
final class JobRestartServiceTest extends TestCase
{
    #[Test]
    public function restartAsAdminRejectsDestructiveLifecycleJobs(): void
    {
        $restartJobRepository    = new RestartJobRepository();
        $restartJobLogRepository = new RestartJobLogRepository();
        $originalJob             = Job::schedule(
            organizationId: 'org-1',
            type: 'organization.delete',
            payload: [
                'deletion_run_id' => 'run-1',
                'stage'           => 'finalize',
            ],
            priority: 7,
            maxAttempts: 1,
            idempotencyKey: 'organization-delete:org-1',
        );
        $originalJob->setIdentifier(Uuid::uuid7());
        $originalJob->setCreatedAt(Chronos::parse('2026-06-01T00:00:00+00:00'));
        $originalJob->markRunning('worker-1');
        $originalJob->markFailed('cleanup failed');
        $restartJobRepository->save($originalJob);

        $restarted = new JobRestartService($restartJobRepository, $restartJobLogRepository)
            ->restartAsAdmin($originalJob->getIdentifier()->toString(), 'admin-1')
        ;

        self::assertNull($restarted);
        self::assertCount(1, $restartJobRepository->jobs);
        self::assertSame([], $restartJobLogRepository->logs);
    }

    #[Test]
    public function restartAsAdminReturnsNullForUnknownJob(): void
    {
        $restartJobLogRepository = new RestartJobLogRepository();

        $restarted = new JobRestartService(new RestartJobRepository(), $restartJobLogRepository)
            ->restartAsAdmin('missing', 'admin-1')
        ;

        self::assertNull($restarted);
        self::assertSame([], $restartJobLogRepository->logs);
    }
}

final class RestartJobRepository implements JobRepositoryInterface
{
    /** @var array<string, Job> */
    public array $jobs = [];

    public function save(Job $job): void
    {
        try {
            $job->getIdentifier();
        } catch (Error) {
            $job->setIdentifier(Uuid::uuid7());
        }

        $job->setCreatedAt(Chronos::parse('2026-06-02T00:00:00+00:00'));
        $this->jobs[$job->getIdentifier()->toString()] = $job;
    }

    public function findById(string $id): ?Job
    {
        return $this->jobs[$id] ?? null;
    }

    public function releaseTerminalIdempotencyKey(string $organizationId, string $type, string $idempotencyKey): void
    {
        throw new LogicException('Terminal key release is not supported by this test repository.');
    }

    public function findByIdempotencyKey(string $organizationId, string $type, string $idempotencyKey): ?Job
    {
        return null;
    }

    public function advanceLifecycle(string $id, string $organizationId, string $fromStatus, string $toStatus): bool
    {
        return false;
    }

    public function findSourceDeleteJobs(string $organizationId, string $sourceId): array
    {
        return [];
    }

    public function deleteOlderThan(Chronos $threshold): int
    {
        return 0;
    }

    public function findByOrganization(string $organizationId, array $filters = []): array
    {
        return [];
    }

    public function dequeue(string $workerId, int $timeoutSeconds = 30): ?Job
    {
        return null;
    }

    public function findStuck(int $timeoutMinutes = 30): array
    {
        return [];
    }

    public function findPaginatedAll(PaginationCriteria $paginationCriteria, JobFilterCriteria $jobFilterCriteria): array
    {
        return [
            'items'       => array_values($this->jobs),
            'total'       => count($this->jobs),
            'currentPage' => $paginationCriteria->page,
            'perPage'     => $paginationCriteria->perPage,
        ];
    }

    public function distinctTypes(?string $organizationId = null): array
    {
        return [];
    }

    public function countAllByStatus(JobFilterCriteria $jobFilterCriteria): array
    {
        return [
            'pending'   => 0,
            'running'   => 0,
            'completed' => 0,
            'failed'    => 0,
            'canceled'  => 0,
        ];
    }

    public function queueProgress(?string $organizationId = null): array
    {
        unset($organizationId);

        return [
            'pending'                  => 0,
            'running'                  => 0,
            'failed'                   => 0,
            'retrying'                 => 0,
            'stuck'                    => 0,
            'oldest_ready_age_seconds' => null,
        ];
    }
}

final class RestartJobLogRepository implements JobLogRepositoryInterface
{
    /** @var list<JobLog> */
    public array $logs = [];

    public function save(JobLog $jobLog): void
    {
        $jobLog->setIdentifier(Uuid::uuid7());
        $jobLog->setCreatedAt(Chronos::parse('2026-06-02T00:00:00+00:00'));
        $this->logs[] = $jobLog;
    }

    public function findByJob(string $jobId): array
    {
        return array_values(array_filter(
            $this->logs,
            static fn (JobLog $jobLog): bool => $jobId === $jobLog->jobId(),
        ));
    }

    public function countWarningsByJobIds(array $jobIds): array
    {
        return [];
    }

    public function deleteOlderThan(Chronos $threshold): int
    {
        return 0;
    }
}
