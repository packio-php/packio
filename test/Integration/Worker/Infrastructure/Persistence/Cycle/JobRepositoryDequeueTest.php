<?php

declare(strict_types=1);

namespace PackioTest\Integration\Worker\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

final class JobRepositoryDequeueTest extends IntegrationTestCase
{
    #[Test]
    public function dequeueTakesAJobThatAnotherWorkerChangedSinceThisWorkerLoadedIt(): void
    {
        $jobRepository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobRepository);

        $job = Job::schedule(
            'dequeue-stale-organization',
            'test.dequeue-stale',
            [],
            priority: 1_000_000,
            scheduledAt: new Chronos('-1 second'),
        );
        $job->setIdentifier(Uuid::uuid7());
        $jobRepository->save($job);

        // Another worker took the job and returned it to the queue: the row is
        // pending again, but this worker still holds the old version in memory.
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $databaseProvider->database()->execute(
            'UPDATE jobs SET version = version + 1 WHERE uuid = ?',
            [$job->getIdentifier()->toString()],
        );

        $dequeued = $jobRepository->dequeue('late-worker', 2);

        self::assertInstanceOf(Job::class, $dequeued);
        self::assertSame($job->getIdentifier()->toString(), $dequeued->getIdentifier()->toString());
        self::assertSame(Job::STATUS_RUNNING, $dequeued->status());
    }
}
