<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Adapter\Worker;

use Packio\Source\Application\Dto\SourceDeletionExecutionResult;
use Packio\Source\Application\Exception\SourceDeletionExecutionLeaseLostException;
use Packio\Source\Application\Service\SourceDeletionExecutionInterface;
use Packio\Source\Application\Service\SourceDeletionOutcome;
use Packio\Source\Infrastructure\Adapter\Worker\SourceDeleteJobHandler;
use Packio\Worker\Api\Exception\WorkerRestartRequiredException;
use Packio\Worker\Api\Job\JobLoggerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SourceDeleteJobHandler::class)]
final class SourceDeleteJobHandlerTest extends TestCase
{
    #[Test]
    #[DataProvider('outcomes')]
    public function mapsOutcome(SourceDeletionOutcome $sourceDeletionOutcome, bool $success, bool $retry): void
    {
        $execution = $this->createMock(SourceDeletionExecutionInterface::class);
        $execution->expects(self::once())->method('execute')
            ->with('source-1', 'org-1', 'run-1', '', self::isInstanceOf(LoggerInterface::class))
            ->willReturn(new SourceDeletionExecutionResult($sourceDeletionOutcome, [
                'source_id' => 'source-1',
            ], 'Execution failed.'))
        ;
        $sourceDeleteJobHandler = new SourceDeleteJobHandler($execution);
        $jobResult              = $sourceDeleteJobHandler->handle([
            'source_id'       => 'source-1',
            'organization_id' => 'org-1',
            'deletion_run_id' => 'run-1',
        ], $this->createStub(JobLoggerInterface::class));
        self::assertSame($success, $jobResult->isSuccess());
        self::assertSame($retry, $jobResult->shouldRetry());
        self::assertSame($sourceDeletionOutcome->value, $jobResult->getData()['outcome']);
        self::assertSame('source-1', $jobResult->getData()['source_id']);
    }

    /** @return list<array{SourceDeletionOutcome, bool, bool}> */
    public static function outcomes(): array
    {
        return [
            [SourceDeletionOutcome::Deleted, true, true],
            [SourceDeletionOutcome::AlreadyDeleted, true, true],
            [SourceDeletionOutcome::Blocked, false, true],
            [SourceDeletionOutcome::RetryableFailure, false, true],
            [SourceDeletionOutcome::StaleLease, true, true],
        ];
    }

    #[Test]
    public function rejectsInvalidPayloadWithoutCallingExecution(): void
    {
        $execution = $this->createMock(SourceDeletionExecutionInterface::class);
        $execution->expects(self::never())->method('execute');
        $jobResult = new SourceDeleteJobHandler($execution)->handle([], $this->createStub(JobLoggerInterface::class));
        self::assertFalse($jobResult->isSuccess());
    }

    #[Test]
    public function passesMissingGenerationToExecutionForLifecycleDecision(): void
    {
        $execution = $this->createMock(SourceDeletionExecutionInterface::class);
        $execution->expects(self::once())->method('execute')->with('source-1', 'org-1', '', '', self::isInstanceOf(LoggerInterface::class))
            ->willReturn(new SourceDeletionExecutionResult(SourceDeletionOutcome::StaleLease, [
                'stale_generation' => true,
            ]))
        ;
        $jobResult = new SourceDeleteJobHandler($execution)->handle([
            'source_id'       => 'source-1',
            'organization_id' => 'org-1',
        ], $this->createStub(JobLoggerInterface::class));
        self::assertTrue($jobResult->isSuccess());
        self::assertTrue($jobResult->getData()['stale_generation']);
    }

    #[Test]
    public function leaseLossRestartsWorker(): void
    {
        $execution = $this->createStub(SourceDeletionExecutionInterface::class);
        $execution->method('execute')->willThrowException(new SourceDeletionExecutionLeaseLostException('lost'));

        try {
            new SourceDeleteJobHandler($execution)->handle([
                'source_id'       => 'source-1',
                'organization_id' => 'org-1',
            ], $this->createStub(JobLoggerInterface::class));
            self::fail('Expected WorkerRestartRequiredException.');
        } catch (WorkerRestartRequiredException $e) {
            self::assertSame('lost', $e->getMessage());
            self::assertInstanceOf(SourceDeletionExecutionLeaseLostException::class, $e->getPrevious());
        }
    }
}
