<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Adapter;

use Packio\Source\Infrastructure\Adapter\WorkerJobSchedulerAdapter;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\JobApiFacadeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkerJobSchedulerAdapter::class)]
final class WorkerJobSchedulerAdapterTest extends TestCase
{
    #[Test]
    #[DataProvider('statuses')]
    public function enqueueOnceMapsTerminalStatus(string $status, bool $terminal): void
    {
        $facade = $this->createMock(JobApiFacadeInterface::class);
        $facade->expects(self::once())
            ->method('enqueueOnce')
            ->with('org-1', 'source.delete', 'key', [
                'a' => 1,
            ], 5)
            ->willReturn($this->job('job-1', $status))
        ;

        $scheduledJob = new WorkerJobSchedulerAdapter($facade)->enqueueOnce('org-1', 'source.delete', 'key', [
            'a' => 1,
        ], 5);

        self::assertSame('job-1', $scheduledJob->id);
        self::assertSame($terminal, $scheduledJob->terminal);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function statuses(): iterable
    {
        yield 'completed' => ['completed', true];

        yield 'failed' => ['failed', true];

        yield 'canceled' => ['canceled', true];

        yield 'pending' => ['pending', false];

        yield 'running' => ['running', false];
    }

    #[Test]
    public function enqueueOnceHeldReturnsJobId(): void
    {
        $facade = $this->createMock(JobApiFacadeInterface::class);
        $facade->expects(self::once())
            ->method('enqueueOnceHeld')
            ->with('org-1', 'source.delete', 'key', [], 0)
            ->willReturn($this->job('job-2', 'pending'))
        ;

        self::assertSame('job-2', new WorkerJobSchedulerAdapter($facade)->enqueueOnceHeld('org-1', 'source.delete', 'key', []));
    }

    #[Test]
    public function enqueueSourceSyncOnceReturnsJobId(): void
    {
        $facade = $this->createMock(JobApiFacadeInterface::class);
        $facade->expects(self::once())
            ->method('enqueueSourceSyncOnce')
            ->with('org-1', 'src-1', 'key', [], 0)
            ->willReturn($this->job('job-3', 'pending'))
        ;

        self::assertSame('job-3', new WorkerJobSchedulerAdapter($facade)->enqueueSourceSyncOnce('org-1', 'src-1', 'key', []));
    }

    private function job(string $id, string $status): JobDto
    {
        return new JobDto($id, 'org-1', 'source.delete', [], null, $status, 0, '2026-01-01 00:00:00', null, null, 0, 3, null, null, null, '2026-01-01 00:00:00', null);
    }
}
