<?php

declare(strict_types=1);

namespace PackioTest\Unit\Worker;

use Cake\Chronos\Chronos;
use Common\Runtime\RuntimeResetterInterface;
use LogicException;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Worker\Api\Dto\JobFilterCriteria;
use Packio\Worker\Api\Exception\WorkerRestartRequiredException;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use Packio\Worker\Infrastructure\Cli\WorkerRunCommand;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function array_shift;
use function array_values;

#[CoversClass(WorkerRunCommand::class)]
#[AllowMockObjectsWithoutExpectations]
final class WorkerRunCommandTest extends TestCase
{
    #[Test]
    public function commandExitsForSupervisorRestartAfterFailClosedResourceLoss(): void
    {
        $job = $this->createScheduledJob('org-1', 'source.delete', []);

        $repository = $this->createMock(JobRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findStuck')
            ->with(30)
            ->willReturn([])
        ;
        $repository->expects(self::once())
            ->method('dequeue')
            ->willReturn($job)
        ;

        $processor = $this->createMock(JobProcessor::class);
        $processor->expects(self::once())
            ->method('process')
            ->with(self::identicalTo($job))
            ->willThrowException(new WorkerRestartRequiredException('Lease connection lost.'))
        ;

        $runtimeResetter = $this->createMock(RuntimeResetterInterface::class);
        $runtimeResetter->expects(self::once())->method('reset');

        $workerRunCommand = new WorkerRunCommand(
            $repository,
            $processor,
            runtimeResetter: $runtimeResetter,
        );
        $bufferedOutput = new BufferedOutput();

        $exitCode = $workerRunCommand->run(new ArrayInput([
            '--timeout'   => '0',
            '--worker-id' => 'test-worker',
        ]), $bufferedOutput);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('must restart', $bufferedOutput->fetch());
    }

    #[Test]
    public function stuckJobWithoutAttemptsLeftIsMarkedFailed(): void
    {
        $job = $this->createScheduledJob('org-1', 'test.type', []);
        $job->markRunning('w');
        $job->markRunning('w');
        $job->markRunning('w');

        $this->runWithStuckJob($job);

        self::assertSame(Job::STATUS_FAILED, $job->status());
        self::assertSame('Job exceeded the 30 minute running timeout.', $job->errorMessage());
    }

    #[Test]
    public function stuckJobWithAttemptsLeftIsRequeued(): void
    {
        $job = $this->createScheduledJob('org-1', 'test.type', []);
        $job->markRunning('w');

        $this->runWithStuckJob($job);

        self::assertTrue($job->isPending());
        self::assertNull($job->workerId());
    }

    #[Test]
    public function commandProcessesDequeuedJob(): void
    {
        $job = $this->createScheduledJob('org-1', 'test.type', [
            'foo' => 'bar',
        ]);

        $repository = new class($job) implements JobRepositoryInterface {
            public function __construct(private readonly Job $job) {}
            private bool $returnedJob = false;

            public function save(Job $job): void {}

            public function findById(string $id): ?Job
            {
                return null;
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
                if (! $this->returnedJob) {
                    $this->returnedJob = true;

                    return $this->job;
                }

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
        };

        $processor = $this->createMock(JobProcessor::class);
        $processor->expects(self::once())
            ->method('process')
            ->with(self::identicalTo($job))
        ;

        $workerRunCommand = new WorkerRunCommand($repository, $processor);

        $arrayInput = new ArrayInput([
            '--timeout'   => '0',
            '--max-jobs'  => '1',
            '--worker-id' => 'test-worker',
        ]);
        $bufferedOutput = new BufferedOutput();

        $exitCode = $workerRunCommand->run($arrayInput, $bufferedOutput);

        self::assertSame(0, $exitCode);
        $display = $bufferedOutput->fetch();
        self::assertStringContainsString('Processing job', $display);
        self::assertStringContainsString('completed', $display);
        self::assertStringContainsString('shutting down', $display);
    }

    #[Test]
    public function commandResetsRuntimeAfterEachProcessedJob(): void
    {
        $job1 = $this->createScheduledJob('org-1', 'test.type', []);
        $job2 = $this->createScheduledJob('org-1', 'test.type', []);

        $repository = new class($job1, $job2) implements JobRepositoryInterface {
            /** @var array<int, Job> */
            private array $jobs;

            public function __construct(Job ...$jobs)
            {
                $this->jobs = array_values($jobs);
            }

            public function save(Job $job): void {}

            public function findById(string $id): ?Job
            {
                return null;
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
                return array_shift($this->jobs);
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
        };

        $processor = $this->createMock(JobProcessor::class);
        $processor->expects(self::exactly(2))
            ->method('process')
        ;

        $runtimeResetter = $this->createMock(RuntimeResetterInterface::class);
        $runtimeResetter->expects(self::exactly(2))
            ->method('reset')
        ;

        $workerRunCommand = new WorkerRunCommand($repository, $processor, runtimeResetter: $runtimeResetter);

        // The shared PHPUnit process can exceed the worker's default 128M limit under coverage.
        // This test must exit because of max-jobs, independently of the suite's memory usage.
        $arrayInput = new ArrayInput([
            '--timeout'      => '0',
            '--max-jobs'     => '2',
            '--memory-limit' => (string) PHP_INT_MAX,
            '--worker-id'    => 'test-worker',
        ]);
        $bufferedOutput = new BufferedOutput();

        $exitCode = $workerRunCommand->run($arrayInput, $bufferedOutput);

        self::assertSame(0, $exitCode);
        $display = $bufferedOutput->fetch();
        self::assertStringContainsString('reached max jobs (2)', $display);
        self::assertStringContainsString('shutting down (processed: 2 jobs)', $display);
        self::assertStringNotContainsString('reached memory limit', $display);
    }

    #[Test]
    public function commandExitsWhenMaxJobsReached(): void
    {
        $job1 = $this->createScheduledJob('org-1', 'test.type', []);
        $job2 = $this->createScheduledJob('org-1', 'test.type', []);

        $repository = new class($job1, $job2) implements JobRepositoryInterface {
            /** @var array<int, Job> */
            private array $jobs;

            public function __construct(Job ...$jobs)
            {
                $this->jobs = array_values($jobs);
            }

            public function save(Job $job): void {}

            public function findById(string $id): ?Job
            {
                return null;
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
                return array_shift($this->jobs);
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
        };

        $processor = $this->createMock(JobProcessor::class);

        $workerRunCommand = new WorkerRunCommand($repository, $processor);

        $arrayInput = new ArrayInput([
            '--timeout'   => '0',
            '--max-jobs'  => '1',
            '--worker-id' => 'test-worker',
        ]);
        $bufferedOutput = new BufferedOutput();

        $exitCode = $workerRunCommand->run($arrayInput, $bufferedOutput);

        self::assertSame(0, $exitCode);
        $display = $bufferedOutput->fetch();
        self::assertStringContainsString('reached max jobs (1)', $display);
    }

    #[Test]
    public function commandUsesExplicitWorkerId(): void
    {
        $job = $this->createScheduledJob('org-1', 'test.type', []);

        $repository = new class($job) implements JobRepositoryInterface {
            public function __construct(private readonly Job $job) {}
            private bool $returnedJob = false;

            public function save(Job $job): void {}

            public function findById(string $id): ?Job
            {
                return null;
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
                if (! $this->returnedJob) {
                    $this->returnedJob = true;

                    return $this->job;
                }

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
        };

        $processor = $this->createMock(JobProcessor::class);

        $workerRunCommand = new WorkerRunCommand($repository, $processor);

        $arrayInput = new ArrayInput([
            '--timeout'   => '0',
            '--max-jobs'  => '1',
            '--worker-id' => 'my-custom-worker',
        ]);
        $bufferedOutput = new BufferedOutput();

        $exitCode = $workerRunCommand->run($arrayInput, $bufferedOutput);

        self::assertSame(0, $exitCode);
        $display = $bufferedOutput->fetch();
        self::assertStringContainsString('Worker [my-custom-worker] started', $display);
    }

    #[Test]
    public function commandContinuesPollingOnException(): void
    {
        $job = $this->createScheduledJob('org-1', 'test.type', []);

        $repository = new class($job) implements JobRepositoryInterface {
            public function __construct(private readonly Job $job) {}
            private int $callCount = 0;

            public function save(Job $job): void {}

            public function findById(string $id): ?Job
            {
                return null;
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
                ++$this->callCount;

                return match ($this->callCount) {
                    1       => throw new RuntimeException('Database connection lost'),
                    2       => $this->job,
                    default => null,
                };
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
        };

        $processor = $this->createMock(JobProcessor::class);
        $processor->expects(self::once())
            ->method('process')
            ->with(self::identicalTo($job))
        ;

        $workerRunCommand = new WorkerRunCommand($repository, $processor);

        $arrayInput = new ArrayInput([
            '--timeout'   => '0',
            '--max-jobs'  => '1',
            '--worker-id' => 'test-worker',
        ]);
        $bufferedOutput = new BufferedOutput();

        $exitCode = $workerRunCommand->run($arrayInput, $bufferedOutput);

        self::assertSame(0, $exitCode);
        $display = $bufferedOutput->fetch();
        // Should have recovered from the exception and processed the job
        self::assertStringContainsString('Processing job', $display);
        self::assertStringContainsString('completed', $display);
    }

    #[Test]
    public function stopWhenEmptyExitsOnNoJob(): void
    {
        $repository = new class implements JobRepositoryInterface {
            public function save(Job $job): void {}

            public function findById(string $id): ?Job
            {
                return null;
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
        };

        $processor = $this->createMock(JobProcessor::class);

        $workerRunCommand = new WorkerRunCommand($repository, $processor);

        $arrayInput = new ArrayInput([
            '--timeout'         => '0',
            '--stop-when-empty' => true,
        ]);
        $bufferedOutput = new BufferedOutput();

        $exitCode = $workerRunCommand->run($arrayInput, $bufferedOutput);

        self::assertSame(0, $exitCode);
        $display = $bufferedOutput->fetch();
        self::assertStringContainsString('--stop-when-empty', $display);
    }

    private function runWithStuckJob(Job $job): void
    {
        $repository = $this->createMock(JobRepositoryInterface::class);
        $repository->method('findStuck')->willReturn([$job]);
        $repository->method('dequeue')->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::identicalTo($job));

        new WorkerRunCommand($repository, $this->createMock(JobProcessor::class))->run(new ArrayInput([
            '--timeout'         => '0',
            '--worker-id'       => 'test-worker',
            '--stop-when-empty' => true,
        ]), new BufferedOutput());
    }

    /** @param array<string, mixed> $payload */
    private function createScheduledJob(string $organizationId, string $type, array $payload = []): Job
    {
        $job = Job::schedule($organizationId, $type, $payload);
        $job->setIdentifier(Uuid::uuid7());

        return $job;
    }
}
