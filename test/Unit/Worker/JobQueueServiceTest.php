<?php

declare(strict_types=1);

namespace PackioTest\Unit\Worker;

use Cake\Chronos\Chronos;
use Error;
use LogicException;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Worker\Api\Dto\JobFilterCriteria;
use Packio\Worker\Application\JobQueueService;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;

use function array_slice;

#[CoversClass(JobQueueService::class)]
final class JobQueueServiceTest extends TestCase
{
    #[Test]
    public function enqueueResultDistinguishesNewAndExistingJobs(): void
    {
        $jobQueueJobRepositoryStub = $this->createRepository();
        $jobQueueService           = new JobQueueService($jobQueueJobRepositoryStub);
        $enqueueResult             = $jobQueueService->enqueueOnceWithResult('org', 'source.create', 'key', []);
        $second                    = $jobQueueService->enqueueOnceWithResult('org', 'source.create', 'key', []);
        self::assertTrue($enqueueResult->created);
        self::assertFalse($second->created);
        self::assertSame($enqueueResult->job, $second->job);
        self::assertCount(1, $jobQueueJobRepositoryStub->jobs);
    }

    #[Test]
    public function concurrentInsertIsReportedAsExisting(): void
    {
        $job        = Job::schedule('org', 'source.create', [], idempotencyKey: 'key');
        $repository = $this->createMock(JobRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('findByIdempotencyKey')->with('org', 'source.create', 'key')->willReturnOnConsecutiveCalls(null, $job);
        $repository->expects(self::once())->method('save')->willThrowException(new RepositoryPersistException('unique conflict'));
        $enqueueResult = new JobQueueService($repository)->enqueueOnceWithResult('org', 'source.create', 'key', []);
        self::assertFalse($enqueueResult->created);
        self::assertSame($job, $enqueueResult->job);
    }

    #[Test]
    public function enqueueOnceReusesExistingJobForSameIdempotencyKey(): void
    {
        $jobQueueJobRepositoryStub = $this->createRepository();

        $jobQueueService = new JobQueueService($jobQueueJobRepositoryStub);

        $job = $jobQueueService->enqueueOnce('org-1', 'source.sync', 'webhook:one', [
            'source_id' => 'src-1',
        ]);
        $second = $jobQueueService->enqueueOnce('org-1', 'source.sync', 'webhook:one', [
            'source_id' => 'src-1',
        ]);

        self::assertSame($job, $second);
        self::assertCount(1, $jobQueueJobRepositoryStub->jobs);
        self::assertSame('webhook:one', $job->idempotencyKey());
    }

    #[Test]
    public function enqueueSourceSyncOnceCoalescesIntoPendingJobForSameSource(): void
    {
        $jobQueueJobRepositoryStub = $this->createRepository();
        $job                       = Job::schedule('org-1', 'source.sync', [
            'source_id' => 'src-1',
        ], idempotencyKey: 'webhook:one');
        $jobQueueJobRepositoryStub->jobs[] = $job;

        $jobQueueService = new JobQueueService($jobQueueJobRepositoryStub);

        $result = $jobQueueService->enqueueSourceSyncOnce('org-1', 'src-1', 'webhook:two', [
            'source_id' => 'src-1',
        ]);

        self::assertSame($job, $result);
        self::assertCount(1, $jobQueueJobRepositoryStub->jobs);
        self::assertSame('webhook:one', $result->idempotencyKey());
    }

    #[Test]
    public function enqueueSourceSyncOnceEnqueuesNewJobWhenNoPendingJobExists(): void
    {
        $jobQueueJobRepositoryStub = $this->createRepository();

        $jobQueueService = new JobQueueService($jobQueueJobRepositoryStub);

        $job = $jobQueueService->enqueueSourceSyncOnce('org-1', 'src-1', 'webhook:one', [
            'source_id' => 'src-1',
        ]);

        self::assertCount(1, $jobQueueJobRepositoryStub->jobs);
        self::assertSame($job, $jobQueueJobRepositoryStub->jobs[0]);
        self::assertSame('webhook:one', $job->idempotencyKey());
        self::assertSame('source.sync', $job->type());
    }

    #[Test]
    public function enqueueSourceSyncOnceEnqueuesNewJobWhenPreviousSyncAlreadyCompleted(): void
    {
        $jobQueueJobRepositoryStub = $this->createRepository();
        $job                       = Job::schedule('org-1', 'source.sync', [
            'source_id' => 'src-1',
        ], idempotencyKey: 'webhook:one');
        $job->markCompleted();
        $jobQueueJobRepositoryStub->jobs[] = $job;

        $jobQueueService = new JobQueueService($jobQueueJobRepositoryStub);

        $result = $jobQueueService->enqueueSourceSyncOnce('org-1', 'src-1', 'webhook:two', [
            'source_id' => 'src-1',
        ]);

        self::assertNotSame($job, $result);
        self::assertCount(2, $jobQueueJobRepositoryStub->jobs);
        self::assertSame('webhook:two', $result->idempotencyKey());
        self::assertSame(Job::STATUS_PENDING, $result->status());
    }

    #[Test]
    public function heldJobRequiresAuditConfirmationBeforeItCanBePublished(): void
    {
        $jobQueueJobRepositoryStub = $this->createRepository();
        $jobQueueService           = new JobQueueService($jobQueueJobRepositoryStub);

        $job = $jobQueueService->enqueueOnceHeld('org-1', 'source.delete', 'source-delete:src-1:run-1', [
            'source_id' => 'src-1',
        ]);

        self::assertSame(Job::STATUS_HELD, $job->status());
        self::assertFalse($jobQueueService->publish($job->getIdentifier()->toString(), 'org-1'));
        self::assertTrue($jobQueueService->confirmAudit($job->getIdentifier()->toString(), 'org-1'));
        self::assertSame(Job::STATUS_AUDITED, $job->status());
        self::assertTrue($jobQueueService->publish($job->getIdentifier()->toString(), 'org-1'));
        self::assertSame(Job::STATUS_PENDING, $job->status());
    }

    #[Test]
    public function publishAfterAuditResumesAHeldLifecycleIdempotently(): void
    {
        $jobQueueJobRepositoryStub = $this->createRepository();
        $jobQueueService           = new JobQueueService($jobQueueJobRepositoryStub);

        $job = $jobQueueService->enqueueOnceHeld('org-1', 'source.delete', 'source-delete:src-1:run-1', [
            'source_id' => 'src-1',
        ]);

        self::assertTrue($jobQueueService->publishAfterAudit($job->getIdentifier()->toString(), 'org-1'));
        self::assertSame(Job::STATUS_PENDING, $job->status());
        self::assertTrue($jobQueueService->publishAfterAudit($job->getIdentifier()->toString(), 'org-1'));
    }

    #[Test]
    public function cancelCanCancelAnUnpublishedHeldJob(): void
    {
        $jobQueueJobRepositoryStub = $this->createRepository();
        $jobQueueService           = new JobQueueService($jobQueueJobRepositoryStub);

        $job = $jobQueueService->enqueueOnceHeld('org-1', 'source.delete', 'source-delete:src-1:run-1', [
            'source_id' => 'src-1',
        ]);

        self::assertTrue($jobQueueService->cancel($job->getIdentifier()->toString()));
        self::assertSame(Job::STATUS_CANCELED, $job->status());
    }

    #[Test]
    public function publishAuditedByIdempotencyKeyOnlyPublishesTheExactAuditedLifecycle(): void
    {
        $jobQueueJobRepositoryStub = $this->createRepository();
        $jobQueueService           = new JobQueueService($jobQueueJobRepositoryStub);

        $job = $jobQueueService->enqueueOnceHeld('org-1', 'source.delete', 'source-delete:src-1:run-1', [
            'source_id' => 'src-1',
        ]);

        self::assertFalse($jobQueueService->publishAuditedByIdempotencyKey(
            'org-1',
            'source.delete',
            'source-delete:src-1:run-1',
        ));
        self::assertTrue($jobQueueService->confirmAudit($job->getIdentifier()->toString(), 'org-1'));
        self::assertTrue($jobQueueService->publishAuditedByIdempotencyKey(
            'org-1',
            'source.delete',
            'source-delete:src-1:run-1',
        ));
        self::assertSame(Job::STATUS_PENDING, $job->status());
        self::assertFalse($jobQueueService->publishAuditedByIdempotencyKey(
            'org-1',
            'source.delete',
            'source-delete:src-1:run-1',
        ));
    }

    private function createRepository(): JobQueueJobRepositoryStub
    {
        return new JobQueueJobRepositoryStub();
    }
}

final class JobQueueJobRepositoryStub implements JobRepositoryInterface
{
    /** @var list<Job> */
    public array $jobs = [];

    public function save(Job $job): void
    {
        try {
            $job->getIdentifier();
        } catch (Error) {
            $job->setIdentifier(Uuid::uuid7());
        }

        foreach ($this->jobs as $index => $existingJob) {
            try {
                $existingJob->getIdentifier();
            } catch (Error) {
                $existingJob->setIdentifier(Uuid::uuid7());
            }

            if ($existingJob->getIdentifier()->toString() === $job->getIdentifier()->toString()) {
                $this->jobs[$index] = $job;

                return;
            }
        }

        $this->jobs[] = $job;
    }

    public function findById(string $id): ?Job
    {
        foreach ($this->jobs as $job) {
            if ($job->getIdentifier()->toString() === $id) {
                return $job;
            }
        }

        return null;
    }

    public function releaseTerminalIdempotencyKey(string $organizationId, string $type, string $idempotencyKey): void
    {
        throw new LogicException('Terminal key release is not supported by this test repository.');
    }

    public function findByIdempotencyKey(string $organizationId, string $type, string $idempotencyKey): ?Job
    {
        foreach ($this->jobs as $job) {
            if (
                $job->organizationId() === $organizationId
                && $job->type() === $type
                && $job->idempotencyKey() === $idempotencyKey
            ) {
                return $job;
            }
        }

        return null;
    }

    public function advanceLifecycle(string $id, string $organizationId, string $fromStatus, string $toStatus): bool
    {
        $job = $this->findById($id);
        if (! $job instanceof Job || $job->organizationId() !== $organizationId || $job->status() !== $fromStatus) {
            return false;
        }

        if (Job::STATUS_AUDITED === $toStatus) {
            $job->confirmAudit();
        } elseif (Job::STATUS_PENDING === $toStatus) {
            $job->publish();
        } else {
            return false;
        }

        return true;
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
        $status   = $filters['status'] ?? null;
        $type     = $filters['type'] ?? null;
        $sourceId = $filters['source_id'] ?? null;
        $limit    = $filters['limit'] ?? null;

        $matched = [];
        foreach ($this->jobs as $job) {
            if ($job->organizationId() !== $organizationId) {
                continue;
            }

            if (null !== $status && $job->status() !== $status) {
                continue;
            }

            if (null !== $type && $job->type() !== $type) {
                continue;
            }

            if (null !== $sourceId && ($job->payload()['source_id'] ?? null) !== $sourceId) {
                continue;
            }

            $matched[] = $job;
        }

        if (null !== $limit && $limit > 0) {
            return array_slice($matched, 0, $limit);
        }

        return $matched;
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
            'items'       => [],
            'total'       => 0,
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
