<?php

declare(strict_types=1);

namespace PackioTest\Unit\Worker;

use Cake\Chronos\Chronos;
use LogicException;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Worker\Api\Dto\JobFilterCriteria;
use Packio\Worker\Api\Exception\WorkerRestartRequiredException;
use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Api\Job\JobResult;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;

use function array_filter;
use function array_values;
use function count;

#[CoversClass(JobProcessor::class)]
final class JobProcessorTest extends TestCase
{
    #[Test]
    public function restartRequiredFailureRemainsExecutableAfterTheAttemptBudgetIsExhausted(): void
    {
        $inMemoryJobRepository    = new InMemoryJobRepository();
        $inMemoryJobLogRepository = new InMemoryJobLogRepository();
        $job                      = Job::schedule('org-1', 'test.restart', [], maxAttempts: 1);
        $job->setIdentifier(Uuid::uuid7());
        $job->markRunning('worker-1');

        $jobProcessor = new JobProcessor($inMemoryJobRepository, $inMemoryJobLogRepository);
        $jobProcessor->registerHandler('test.restart', new class implements JobHandlerInterface {
            public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
            {
                throw new WorkerRestartRequiredException('Fail-closed resource was lost.');
            }
        });

        try {
            $jobProcessor->process($job);
            self::fail('Restart-required failures must escape the job processor.');
        } catch (WorkerRestartRequiredException $workerRestartRequiredException) {
            self::assertSame('Fail-closed resource was lost.', $workerRestartRequiredException->getMessage());
        }

        self::assertSame(Job::STATUS_PENDING, $job->status());
        self::assertSame(1, $job->attemptCount());
        self::assertSame(1, $job->maxAttempts());
        self::assertFalse($job->canRetry());
        self::assertSame($job, $inMemoryJobRepository->findById($job->getIdentifier()->toString()));
    }

    #[Test]
    public function restartRequiredFailureStillEscapesWhenItsReconciliationStateCannotBePersisted(): void
    {
        $inMemoryJobRepository              = new InMemoryJobRepository();
        $inMemoryJobRepository->saveFailure = new RuntimeException('Worker database is unavailable.');
        $inMemoryJobLogRepository           = new InMemoryJobLogRepository();
        $job                                = Job::schedule('org-1', 'test.restart-save', [], maxAttempts: 1);
        $job->setIdentifier(Uuid::uuid7());
        $job->markRunning('worker-1');

        $logCalls = [];
        $logger   = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))
            ->method('log')
            ->willReturnCallback(static function(string $level, string $message, array $context) use (&$logCalls): void {
                $logCalls[] = [$level, $message, $context];
            })
        ;

        $jobProcessor = new JobProcessor($inMemoryJobRepository, $inMemoryJobLogRepository, $logger);
        $jobProcessor->registerHandler('test.restart-save', new class implements JobHandlerInterface {
            public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
            {
                throw new WorkerRestartRequiredException('Fail-closed resource was lost.');
            }
        });

        try {
            $jobProcessor->process($job);
            self::fail('Persistence failure must not replace the restart-required outcome.');
        } catch (WorkerRestartRequiredException $workerRestartRequiredException) {
            self::assertSame('Fail-closed resource was lost.', $workerRestartRequiredException->getMessage());
        }

        self::assertSame('critical', $logCalls[1][0]);
        self::assertSame(
            'Pending reconciliation state could not be persisted; worker restart is still required.',
            $logCalls[1][1],
        );
        self::assertSame($job->getIdentifier()->toString(), $logCalls[1][2]['job_id']);
        self::assertInstanceOf(RuntimeException::class, $logCalls[1][2]['persistence_exception']);
    }

    #[Test]
    #[DataProvider('secondaryReconciliationLogFailureProvider')]
    public function restartRequiredFailureSurvivesASecondaryReconciliationLogFailure(
        int $successfulSavesBeforeFailure,
        int $expectedPersistedLogs,
    ): void {
        $inMemoryJobRepository                                  = new InMemoryJobRepository();
        $inMemoryJobLogRepository                               = new InMemoryJobLogRepository();
        $inMemoryJobLogRepository->successfulSavesBeforeFailure = $successfulSavesBeforeFailure;
        $inMemoryJobLogRepository->saveFailure                  = new RuntimeException('Job log database is unavailable.');
        $job                                                    = Job::schedule('org-1', 'test.restart-log', [], maxAttempts: 1);
        $job->setIdentifier(Uuid::uuid7());
        $job->markRunning('worker-1');
        $workerRestartRequiredException = new WorkerRestartRequiredException('Mutation commit outcome is unknown.');

        $jobProcessor = new JobProcessor($inMemoryJobRepository, $inMemoryJobLogRepository);
        $jobProcessor->registerHandler('test.restart-log', new readonly class($workerRestartRequiredException) implements JobHandlerInterface {
            public function __construct(private WorkerRestartRequiredException $workerRestartRequiredException) {}

            public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
            {
                throw $this->workerRestartRequiredException;
            }
        });

        try {
            $jobProcessor->process($job);
            self::fail('Secondary job-log failures must not replace the restart-required outcome.');
        } catch (WorkerRestartRequiredException $caught) {
            self::assertSame($workerRestartRequiredException, $caught);
        }

        self::assertSame(Job::STATUS_PENDING, $job->status());
        self::assertSame($job, $inMemoryJobRepository->findById($job->getIdentifier()->toString()));
        self::assertCount($expectedPersistedLogs, $inMemoryJobLogRepository->logs);
        self::assertSame('Starting job processing', $inMemoryJobLogRepository->logs[0]->message());
    }

    /** @return iterable<string, array{int, int}> */
    public static function secondaryReconciliationLogFailureProvider(): iterable
    {
        yield 'error log fails' => [1, 1];

        yield 'warning log fails' => [2, 2];
    }

    #[Test]
    public function failureLogsDoNotPersistExceptionDetailsForUi(): void
    {
        $inMemoryJobRepository    = new InMemoryJobRepository();
        $inMemoryJobLogRepository = new InMemoryJobLogRepository();
        $job                      = Job::schedule('org-1', 'test.fails', [], maxAttempts: 1);
        $job->setIdentifier(Uuid::uuid7());
        $job->markRunning('worker-1');

        $jobProcessor = new JobProcessor($inMemoryJobRepository, $inMemoryJobLogRepository);
        $jobProcessor->registerHandler('test.fails', new class implements JobHandlerInterface {
            public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
            {
                throw new RuntimeException('Sensitive failure detail');
            }
        });

        $jobProcessor->process($job);

        $errorLogs = array_values(array_filter(
            $inMemoryJobLogRepository->logs,
            static fn (JobLog $jobLog): bool => JobLog::LEVEL_ERROR === $jobLog->level(),
        ));

        self::assertCount(1, $errorLogs);
        self::assertSame('Job failed: Sensitive failure detail', $errorLogs[0]->message());
        self::assertSame([], $errorLogs[0]->context());
        self::assertSame('failed', $job->status());
        self::assertSame('Sensitive failure detail', $job->errorMessage());
        self::assertNull($job->errorDetails());
    }

    #[Test]
    public function permanentFailureMessageIsRedacted(): void
    {
        $job = Job::schedule('org-1', 'test.fails', [], maxAttempts: 1);
        $job->setIdentifier(Uuid::uuid7());
        $job->markRunning('worker-1');

        $jobProcessor = new JobProcessor(new InMemoryJobRepository(), new InMemoryJobLogRepository());
        $jobProcessor->registerHandler('test.fails', new class implements JobHandlerInterface {
            public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
            {
                throw new RuntimeException('fetch https://user:pass@example.com/repo.git failed');
            }
        });

        $jobProcessor->process($job);

        self::assertNotNull($job->errorMessage());
        self::assertStringNotContainsString('pass', $job->errorMessage());
    }
}
final class InMemoryJobRepository implements JobRepositoryInterface
{
    /** @var array<string, Job> */
    public array $jobs = [];

    public ?RuntimeException $saveFailure = null;

    public function save(Job $job): void
    {
        if ($this->saveFailure instanceof RuntimeException) {
            throw $this->saveFailure;
        }

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

final class InMemoryJobLogRepository implements JobLogRepositoryInterface
{
    /** @var list<JobLog> */
    public array $logs = [];

    public int $successfulSavesBeforeFailure = PHP_INT_MAX;

    public ?RuntimeException $saveFailure = null;

    public function save(JobLog $jobLog): void
    {
        if (
            $this->saveFailure instanceof RuntimeException
            && count($this->logs) >= $this->successfulSavesBeforeFailure
        ) {
            throw $this->saveFailure;
        }

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
