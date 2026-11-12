<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Adapter\Worker;

use Packio\Source\Application\Dto\SourceSyncExecutionResult;
use Packio\Source\Application\Service\SourceSyncExecutionInterface;
use Packio\Source\Application\Service\SourceSyncOutcome;
use Packio\Source\Infrastructure\Adapter\Worker\SourceSyncJobHandler;
use Packio\Worker\Api\Job\JobLoggerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SourceSyncJobHandler::class)]
final class SourceSyncJobHandlerTest extends TestCase
{
    #[Test]
    #[DataProvider('outcomes')]
    public function mapsOutcome(SourceSyncOutcome $sourceSyncOutcome, bool $success, bool $retry): void
    {
        $execution = $this->createMock(SourceSyncExecutionInterface::class);
        $execution->expects(self::once())->method('execute')
            ->with('source-1', 'org-1', self::isInstanceOf(LoggerInterface::class))
            ->willReturn(new SourceSyncExecutionResult($sourceSyncOutcome, [
                'source_id' => 'source-1',
            ], 'Execution failed.'))
        ;
        $sourceSyncJobHandler = new SourceSyncJobHandler($execution);
        $jobResult            = $sourceSyncJobHandler->handle([
            'source_id'       => 'source-1',
            'organization_id' => 'org-1',
        ], $this->createStub(JobLoggerInterface::class));
        self::assertSame($success, $jobResult->isSuccess());
        self::assertSame($retry, $jobResult->shouldRetry());
        self::assertSame($sourceSyncOutcome->value, $jobResult->getData()['outcome']);
        self::assertSame('source-1', $jobResult->getData()['source_id']);
    }

    /** @return list<array{SourceSyncOutcome, bool, bool}> */
    public static function outcomes(): array
    {
        return [
            [SourceSyncOutcome::Synchronized, true, true],
            [SourceSyncOutcome::Degraded, true, true],
            [SourceSyncOutcome::RetryableFailure, false, true],
            [SourceSyncOutcome::AuthorizationRequired, false, false],
            [SourceSyncOutcome::NotFound, false, false],
        ];
    }

    #[Test]
    public function rejectsInvalidPayloadWithoutCallingExecution(): void
    {
        $execution = $this->createMock(SourceSyncExecutionInterface::class);
        $execution->expects(self::never())->method('execute');
        $jobResult = new SourceSyncJobHandler($execution)->handle([], $this->createStub(JobLoggerInterface::class));
        self::assertFalse($jobResult->isSuccess());
    }

    #[Test]
    public function forwardsApplicationWarningsToJobLog(): void
    {
        $execution = $this->createMock(SourceSyncExecutionInterface::class);
        $execution->expects(self::once())->method('execute')->willReturnCallback(static function(string $source, string $org, LoggerInterface $logger): SourceSyncExecutionResult {
            $logger->warning('Archive storage job enqueue failed', [
                'source_id'       => $source,
                'organization_id' => $org,
            ]);

            return new SourceSyncExecutionResult(SourceSyncOutcome::Degraded);
        });
        $logger = $this->createMock(JobLoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('Archive storage job enqueue failed', [
            'source_id'       => 'source-1',
            'organization_id' => 'org-1',
        ]);
        self::assertTrue(new SourceSyncJobHandler($execution)->handle([
            'source_id'       => 'source-1',
            'organization_id' => 'org-1',
        ], $logger)->isSuccess());
    }
}
