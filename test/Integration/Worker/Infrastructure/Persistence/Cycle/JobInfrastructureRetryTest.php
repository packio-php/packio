<?php

declare(strict_types=1);

namespace PackioTest\Integration\Worker\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Worker\Api\Exception\WorkerRestartRequiredException;
use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Api\Job\JobResult;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

final class JobInfrastructureRetryTest extends IntegrationTestCase
{
    #[Test]
    public function restartRequiredJobRemainsPersistedAndDequeueableAfterExhaustingItsAttemptBudget(): void
    {
        $jobRepository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobRepository);
        $jobLogRepository = $this->getService(JobLogRepositoryInterface::class);
        self::assertInstanceOf(JobLogRepositoryInterface::class, $jobLogRepository);

        $job = Job::schedule(
            'infrastructure-retry-organization',
            'test.infrastructure-reconciliation',
            [],
            priority: 1_000_000,
            scheduledAt: new Chronos('-1 second'),
            maxAttempts: 1,
        );
        $job->setIdentifier(Uuid::uuid7());
        $jobRepository->save($job);

        $runningJob = $jobRepository->dequeue('first-worker', 2);
        self::assertInstanceOf(Job::class, $runningJob);
        self::assertSame($job->getIdentifier()->toString(), $runningJob->getIdentifier()->toString());
        self::assertSame(1, $runningJob->attemptCount());
        self::assertFalse($runningJob->canRetry());

        $jobProcessor = new JobProcessor($jobRepository, $jobLogRepository);
        $jobProcessor->registerHandler('test.infrastructure-reconciliation', new class implements JobHandlerInterface {
            public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
            {
                throw new WorkerRestartRequiredException('Mutation commit outcome is unknown.');
            }
        });

        try {
            $jobProcessor->process($runningJob);
            self::fail('Restart-required failure must escape for supervisor restart.');
        } catch (WorkerRestartRequiredException) {
            // Expected after the pending reconciliation state is durable.
        }

        $this->rebuildContainer();
        $jobRepository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobRepository);
        $persistedJob = $jobRepository->findById($job->getIdentifier()->toString());
        self::assertInstanceOf(Job::class, $persistedJob);
        self::assertSame(Job::STATUS_PENDING, $persistedJob->status());
        self::assertSame(1, $persistedJob->attemptCount());
        self::assertSame(1, $persistedJob->maxAttempts());

        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $databaseProvider->database()->execute(
            'UPDATE jobs SET scheduled_at = NOW() - INTERVAL \'1 second\' WHERE uuid = ?',
            [$persistedJob->getIdentifier()->toString()],
        );

        $this->rebuildContainer();
        $jobRepository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobRepository);
        $reconciledJob = $jobRepository->dequeue('replacement-worker', 2);
        self::assertInstanceOf(Job::class, $reconciledJob);
        self::assertSame($job->getIdentifier()->toString(), $reconciledJob->getIdentifier()->toString());
        self::assertSame(Job::STATUS_RUNNING, $reconciledJob->status());
        self::assertSame(2, $reconciledJob->attemptCount());
    }
}
