<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Adapter\Worker;

use Packio\Source\Application\Command\RegisterSourceCommand;
use Packio\Source\Application\Dto\SourceCreationExecutionResult;
use Packio\Source\Application\Service\SourceCreationExecutionInterface;
use Packio\Source\Application\Service\SourceCreationOutcome;
use Packio\Source\Application\Service\SourceWebhookRegistrationOutcome;
use Packio\Source\Infrastructure\Adapter\Worker\SourceCreateJobHandler;
use Packio\Worker\Api\Job\JobLoggerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SourceCreateJobHandler::class)]
final class SourceCreateJobHandlerTest extends TestCase
{
    #[Test]
    #[DataProvider('outcomes')]
    public function mapsOutcome(SourceCreationOutcome $sourceCreationOutcome, bool $success, bool $retry): void
    {
        $execution = $this->createMock(SourceCreationExecutionInterface::class);
        $execution->expects(self::once())->method('execute')
            ->with(self::callback(static fn (RegisterSourceCommand $registerSourceCommand): bool => 'org-1' === $registerSourceCommand->organizationId && 'github' === $registerSourceCommand->provider && 'https://github.com/acme/package' === $registerSourceCommand->resourceId), 'user-1', self::isInstanceOf(LoggerInterface::class))
            ->willReturn(new SourceCreationExecutionResult($sourceCreationOutcome, [
                'source_id' => 'source-1',
            ], 'Execution failed.'))
        ;
        $sourceCreateJobHandler = new SourceCreateJobHandler($execution);
        $jobResult              = $sourceCreateJobHandler->handle([
            'organization_id'      => 'org-1',
            'requested_by_user_id' => 'user-1',
            'provider'             => 'github',
            'resource'             => 'https://github.com/acme/package',
        ], $this->createStub(JobLoggerInterface::class));
        self::assertSame($success, $jobResult->isSuccess());
        self::assertSame($retry, $jobResult->shouldRetry());
        self::assertSame($sourceCreationOutcome->value, $jobResult->getData()['outcome']);
        self::assertSame('source-1', $jobResult->getData()['source_id']);
    }

    /** @return list<array{SourceCreationOutcome, bool, bool}> */
    public static function outcomes(): array
    {
        return [
            [SourceCreationOutcome::Created, true, true],
            [SourceCreationOutcome::Forbidden, false, false],
            [SourceCreationOutcome::RetryableFailure, false, true],
        ];
    }

    #[Test]
    public function rejectsInvalidPayloadWithoutCallingExecution(): void
    {
        $execution = $this->createMock(SourceCreationExecutionInterface::class);
        $execution->expects(self::never())->method('execute');
        $jobResult = new SourceCreateJobHandler($execution)->handle([], $this->createStub(JobLoggerInterface::class));
        self::assertFalse($jobResult->isSuccess());
    }

    #[Test]
    public function webhookWarningRemainsSuccessfulAndVisibleInWorkerResult(): void
    {
        $execution = $this->createMock(SourceCreationExecutionInterface::class);
        $execution->expects(self::once())->method('execute')->willReturn(new SourceCreationExecutionResult(
            SourceCreationOutcome::Created,
            webhook: SourceWebhookRegistrationOutcome::Warning,
        ));
        $jobResult = new SourceCreateJobHandler($execution)->handle([
            'organization_id'      => 'org-1',
            'requested_by_user_id' => 'user-1',
            'provider'             => 'github',
            'resource'             => 'acme/package',
        ], $this->createStub(JobLoggerInterface::class));
        self::assertTrue($jobResult->isSuccess());
        self::assertSame('warning', $jobResult->getData()['webhook_status']);
    }
}
