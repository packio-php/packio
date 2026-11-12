<?php

declare(strict_types=1);

namespace PackioTest\Integration\Worker\Infrastructure\Persistence\Cycle;

use Packio\Worker\Application\JobQueueService;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SourceCreationQueueTest extends IntegrationTestCase
{
    #[DataProvider('statuses')]
    public function testOnlyTerminalJobsReleaseTheirKey(string $status, bool $createsNew): void
    {
        $repository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $repository);
        $logs = $this->getService(JobLogRepositoryInterface::class);
        self::assertInstanceOf(JobLogRepositoryInterface::class, $logs);
        $job = new Job(organizationId: 'reconnect-org', type: 'source.create', payload: [
            'idempotency_key' => 'connect-key',
            'resource'        => 'old',
        ], idempotencyKey: 'connect-key', status: $status);
        $repository->save($job);
        $oldId = $job->getIdentifier()->toString();
        $logs->save(JobLog::record($oldId, JobLog::LEVEL_INFO, 'Previous import'));
        $jobQueueService = new JobQueueService($repository);

        $enqueueResult = $jobQueueService->enqueueSourceCreation('reconnect-org', 'connect-key', [
            'resource' => 'new',
        ]);
        self::assertSame($createsNew, $enqueueResult->created);
        self::assertSame('connect-key', $enqueueResult->job->idempotencyKey());
        self::assertSame(! $createsNew, $oldId === $enqueueResult->job->getIdentifier()->toString());
        $history = $repository->findById($oldId);
        self::assertInstanceOf(Job::class, $history);
        self::assertSame($status, $history->status());
        self::assertSame($job->payload(), $history->payload());
        self::assertSame($createsNew ? null : 'connect-key', $history->idempotencyKey());
        self::assertCount(1, $logs->findByJob($oldId));

        $duplicate = $jobQueueService->enqueueSourceCreation('reconnect-org', 'connect-key', []);
        self::assertFalse($duplicate->created);
        self::assertSame($enqueueResult->job->getIdentifier()->toString(), $duplicate->job->getIdentifier()->toString());
    }

    /** @return iterable<string, array{string, bool}> */
    public static function statuses(): iterable
    {
        yield 'completed' => [Job::STATUS_COMPLETED, true];

        yield 'failed' => [Job::STATUS_FAILED, true];

        yield 'canceled' => [Job::STATUS_CANCELED, true];

        yield 'pending' => [Job::STATUS_PENDING, false];

        yield 'running' => [Job::STATUS_RUNNING, false];

        yield 'held' => [Job::STATUS_HELD, false];

        yield 'audited' => [Job::STATUS_AUDITED, false];
    }

    public function testOtherOrganizationsAndJobTypesKeepTheirKeys(): void
    {
        $repository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $repository);
        $otherOrg  = new Job(organizationId: 'other-org', type: 'source.create', idempotencyKey: 'key', status: Job::STATUS_COMPLETED);
        $otherType = new Job(organizationId: 'org', type: 'source.sync', idempotencyKey: 'key', status: Job::STATUS_COMPLETED);
        $repository->save($otherOrg);
        $repository->save($otherType);
        $jobQueueService = new JobQueueService($repository);
        self::assertTrue($jobQueueService->enqueueSourceCreation('org', 'key', [])->created);
        self::assertSame($otherOrg, $repository->findByIdempotencyKey('other-org', 'source.create', 'key'));
        self::assertFalse($jobQueueService->enqueueOnceWithResult('org', 'source.sync', 'key', [])->created);
        self::assertSame($otherType, $repository->findByIdempotencyKey('org', 'source.sync', 'key'));
    }

    public function testConcurrentInsertionAfterTerminalKeyReleaseReturnsTheWinner(): void
    {
        $repository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $repository);
        $old = new Job(organizationId: 'race-org', type: 'source.create', idempotencyKey: 'key', status: Job::STATUS_COMPLETED);
        $repository->save($old);

        $racingRepository = $this->createMock(JobRepositoryInterface::class);
        $racingRepository->method('releaseTerminalIdempotencyKey')->willReturnCallback($repository->releaseTerminalIdempotencyKey(...));
        $racingRepository->method('findByIdempotencyKey')->willReturnCallback($repository->findByIdempotencyKey(...));
        $racingRepository->expects(self::once())->method('save')->willReturnCallback(static function(Job $job) use ($repository): void {
            // A competing request inserts after our lookup but before our insert.
            $enqueueResult = new JobQueueService($repository)->enqueueSourceCreation('race-org', 'key', [
                'winner' => true,
            ]);
            self::assertTrue($enqueueResult->created);
            $repository->save($job);
        });

        $enqueueResult = new JobQueueService($racingRepository)->enqueueSourceCreation('race-org', 'key', [
            'winner' => false,
        ]);
        self::assertFalse($enqueueResult->created);
        self::assertSame([
            'winner' => true,
        ], $enqueueResult->job->payload());
        self::assertCount(2, $repository->findByOrganization('race-org'));
    }
}
