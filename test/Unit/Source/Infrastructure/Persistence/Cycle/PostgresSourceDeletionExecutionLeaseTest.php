<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\Driver\DriverInterface;
use Cycle\Database\StatementInterface;
use Packio\Source\Application\Exception\SourceDeletionExecutionLeaseLostException;
use Packio\Source\Domain\Value\SourceDeletionExecutionLock;
use Packio\Source\Infrastructure\Persistence\Cycle\PostgresSourceDeletionExecutionLease;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use RuntimeException;

#[CoversClass(PostgresSourceDeletionExecutionLease::class)]
final class PostgresSourceDeletionExecutionLeaseTest extends TestCase
{
    #[Test]
    public function failedLeaseCommitRollsBackTheStillUncommittedMutationBoundary(): void
    {
        $sourceId       = 'source-1';
        $organizationId = 'organization-1';
        $deletionRunId  = 'run-1';
        $events         = [];

        $leaseStatement = $this->createStub(StatementInterface::class);
        $leaseStatement->method('fetchColumn')->willReturn(1);
        $mutationStatement = $this->createStub(StatementInterface::class);
        $mutationStatement->method('fetchColumn')->willReturn(1);

        $leaseDriver = $this->createMock(DriverInterface::class);
        $leaseDriver->expects(self::once())
            ->method('query')
            ->with('SELECT 1')
            ->willReturn($leaseStatement)
        ;
        $leaseDriver->expects(self::once())
            ->method('commitTransaction')
            ->willReturnCallback(static function() use (&$events): never {
                $events[] = 'lease_commit';

                throw new RuntimeException('Lease connection disappeared during commit.');
            })
        ;
        $leaseDriver->method('getTransactionLevel')->willReturn(1);
        $leaseDriver->expects(self::once())
            ->method('rollbackTransaction')
            ->willReturnCallback(static function() use (&$events): void {
                $events[] = 'lease_rollback';
            })
        ;

        $mutationDriver = $this->createMock(DriverInterface::class);
        $mutationDriver->expects(self::once())
            ->method('query')
            ->with('SELECT 1')
            ->willReturn($mutationStatement)
        ;
        $mutationDriver->expects(self::never())->method('commitTransaction');
        $mutationDriver->method('getTransactionLevel')->willReturn(1);
        $mutationDriver->expects(self::once())
            ->method('rollbackTransaction')
            ->willReturnCallback(static function() use (&$events): void {
                $events[] = 'mutation_rollback';
            })
        ;

        $leaseDatabase = $this->createMock(DatabaseInterface::class);
        $leaseDatabase->method('getDriver')->with(DatabaseInterface::WRITE)->willReturn($leaseDriver);
        $mutationDatabase = $this->createMock(DatabaseInterface::class);
        $mutationDatabase->method('getDriver')->with(DatabaseInterface::WRITE)->willReturn($mutationDriver);

        $databaseProvider = $this->createStub(DatabaseProviderInterface::class);
        $databaseProvider->method('database')->willReturnCallback(
            static fn (?string $database = null): DatabaseInterface => 'source_deletion_lease' === $database
                ? $leaseDatabase
                : $mutationDatabase,
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical');

        $postgresSourceDeletionExecutionLease = new PostgresSourceDeletionExecutionLease($databaseProvider, $logger);
        new ReflectionProperty($postgresSourceDeletionExecutionLease, 'heldLockKey')->setValue(
            $postgresSourceDeletionExecutionLease,
            SourceDeletionExecutionLock::key($sourceId, $organizationId),
        );
        new ReflectionProperty($postgresSourceDeletionExecutionLease, 'heldDeletionRunId')->setValue($postgresSourceDeletionExecutionLease, $deletionRunId);

        try {
            $postgresSourceDeletionExecutionLease->commit($sourceId, $organizationId, $deletionRunId);
            self::fail('Lease commit failure must stop the worker before mutation commit.');
        } catch (SourceDeletionExecutionLeaseLostException $exception) {
            self::assertSame(
                'The atomic source deletion boundary could not be committed.',
                $exception->getMessage(),
            );
        }

        self::assertSame(['lease_commit', 'mutation_rollback', 'lease_rollback'], $events);
    }

    #[Test]
    public function unknownMutationCommitOutcomeIsReportedAfterTheLeaseWasSafelyReleased(): void
    {
        $sourceId       = 'source-1';
        $organizationId = 'organization-1';
        $deletionRunId  = 'run-1';
        $events         = [];

        $leaseStatement = $this->createStub(StatementInterface::class);
        $leaseStatement->method('fetchColumn')->willReturn(1);
        $mutationStatement = $this->createStub(StatementInterface::class);
        $mutationStatement->method('fetchColumn')->willReturn(1);

        $leaseDriver = $this->createMock(DriverInterface::class);
        $leaseDriver->expects(self::once())
            ->method('query')
            ->with('SELECT 1')
            ->willReturn($leaseStatement)
        ;
        $leaseDriver->expects(self::once())
            ->method('commitTransaction')
            ->willReturnCallback(static function() use (&$events): bool {
                $events[] = 'lease_commit';

                return true;
            })
        ;
        $leaseDriver->method('getTransactionLevel')->willReturn(0);
        $leaseDriver->expects(self::never())->method('rollbackTransaction');

        $mutationDriver = $this->createMock(DriverInterface::class);
        $mutationDriver->expects(self::once())
            ->method('query')
            ->with('SELECT 1')
            ->willReturn($mutationStatement)
        ;
        $mutationDriver->expects(self::once())
            ->method('commitTransaction')
            ->willReturnCallback(static function() use (&$events): never {
                $events[] = 'mutation_commit_outcome_unknown';

                throw new RuntimeException('Mutation COMMIT acknowledgement was lost.');
            })
        ;
        $mutationDriver->method('getTransactionLevel')->willReturn(1);
        $mutationDriver->expects(self::once())
            ->method('rollbackTransaction')
            ->willReturnCallback(static function() use (&$events): void {
                $events[] = 'mutation_rollback';
            })
        ;

        $leaseDatabase = $this->createMock(DatabaseInterface::class);
        $leaseDatabase->method('getDriver')->with(DatabaseInterface::WRITE)->willReturn($leaseDriver);
        $mutationDatabase = $this->createMock(DatabaseInterface::class);
        $mutationDatabase->method('getDriver')->with(DatabaseInterface::WRITE)->willReturn($mutationDriver);

        $databaseProvider = $this->createStub(DatabaseProviderInterface::class);
        $databaseProvider->method('database')->willReturnCallback(
            static fn (?string $database = null): DatabaseInterface => 'source_deletion_lease' === $database
                ? $leaseDatabase
                : $mutationDatabase,
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical');

        $postgresSourceDeletionExecutionLease = new PostgresSourceDeletionExecutionLease($databaseProvider, $logger);
        new ReflectionProperty($postgresSourceDeletionExecutionLease, 'heldLockKey')->setValue(
            $postgresSourceDeletionExecutionLease,
            SourceDeletionExecutionLock::key($sourceId, $organizationId),
        );
        new ReflectionProperty($postgresSourceDeletionExecutionLease, 'heldDeletionRunId')->setValue($postgresSourceDeletionExecutionLease, $deletionRunId);

        try {
            $postgresSourceDeletionExecutionLease->commit($sourceId, $organizationId, $deletionRunId);
            self::fail('An unknown mutation commit outcome must require worker reconciliation.');
        } catch (SourceDeletionExecutionLeaseLostException $exception) {
            self::assertSame(
                'The atomic source deletion boundary could not be committed.',
                $exception->getMessage(),
            );
        }

        self::assertSame(
            ['lease_commit', 'mutation_commit_outcome_unknown', 'mutation_rollback'],
            $events,
        );
    }
}
