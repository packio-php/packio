<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\Driver\DriverInterface;
use Packio\Source\Application\Exception\SourceDeletionExecutionLeaseLostException;
use Packio\Source\Application\Service\SourceDeletionExecutionLeaseInterface;
use Packio\Source\Domain\Value\SourceDeletionExecutionLock;
use Psr\Log\LoggerInterface;
use Throwable;

use function is_array;

final class PostgresSourceDeletionExecutionLease implements SourceDeletionExecutionLeaseInterface
{
    private const string LEASE_DATABASE = 'source_deletion_lease';

    private ?string $heldLockKey = null;

    private ?string $heldDeletionRunId = null;

    public function __construct(
        private readonly DatabaseProviderInterface $databaseProvider,
        private readonly LoggerInterface $logger,
    ) {}

    public function acquire(string $sourceId, string $organizationId, string $deletionRunId): bool
    {
        if (null !== $this->heldLockKey) {
            throw new SourceDeletionExecutionLeaseLostException('The worker already owns a source deletion execution lease.');
        }

        $lockKey        = SourceDeletionExecutionLock::key($sourceId, $organizationId);
        $leaseDriver    = null;
        $mutationDriver = null;

        try {
            $leaseDriver = $this->leaseWriteDriver();
            $leaseDriver->beginTransaction();
            $granted = $leaseDriver->query(
                'SELECT pg_try_advisory_xact_lock(hashtextextended(?, 0))',
                [$lockKey],
            )->fetchColumn();

            if (! $this->isLockGranted($granted)) {
                $leaseDriver->rollbackTransaction();

                return false;
            }

            $mutationDriver = $this->mutationWriteDriver();
            $mutationDriver->beginTransaction();
            $rows = $mutationDriver->getQueryBuilder()
                ->selectQuery($this->databaseProvider->database()->getPrefix())
                ->from('sources')
                ->columns('status', 'deletion_run_id')
                ->where('uuid', $sourceId)
                ->where('organization_id', $organizationId)
                ->forUpdate()
                ->limit(1)
                ->fetchAll()
            ;
            $source            = $rows[0] ?? null;
            $currentGeneration = is_array($source)
                && 'deleting' === (string) ($source['status'] ?? '')
                && $deletionRunId === (string) ($source['deletion_run_id'] ?? '');

            if (! $currentGeneration) {
                $mutationDriver->rollbackTransaction();
                $leaseDriver->rollbackTransaction();

                return false;
            }

            $this->heldLockKey       = $lockKey;
            $this->heldDeletionRunId = $deletionRunId;

            $this->logger->info('Source deletion lease and atomic mutation boundary acquired.', [
                'source_id'       => $sourceId,
                'organization_id' => $organizationId,
                'deletion_run_id' => $deletionRunId,
            ]);

            return true;
        } catch (Throwable $throwable) {
            if ($mutationDriver instanceof DriverInterface) {
                $this->rollbackTransaction($mutationDriver);
            }

            if ($leaseDriver instanceof DriverInterface) {
                $this->rollbackTransaction($leaseDriver);
            }
            $this->clearOwnership();

            $this->logger->critical('Source deletion lease acquisition failed; worker restart is required.', [
                'source_id'       => $sourceId,
                'organization_id' => $organizationId,
                'deletion_run_id' => $deletionRunId,
                'exception'       => $throwable,
            ]);

            throw $throwable instanceof SourceDeletionExecutionLeaseLostException
                ? $throwable
                : new SourceDeletionExecutionLeaseLostException(
                    'The source deletion execution lease could not be acquired.',
                    0,
                    $throwable,
                );
        }
    }

    public function assertHeld(string $sourceId, string $organizationId, string $deletionRunId): void
    {
        $lockKey = SourceDeletionExecutionLock::key($sourceId, $organizationId);
        if ($lockKey !== $this->heldLockKey || $deletionRunId !== $this->heldDeletionRunId) {
            throw new SourceDeletionExecutionLeaseLostException('The source deletion execution lease is not owned by this lifecycle run.');
        }

        try {
            // reconnect=false makes this a fencing checkpoint on the exact
            // PostgreSQL session that acquired the advisory lock.
            $this->leaseWriteDriver()->query('SELECT 1')->fetchColumn();
            $this->mutationWriteDriver()->query('SELECT 1')->fetchColumn();
        } catch (Throwable $throwable) {
            $this->logger->critical('Source deletion lease connection was lost; cleanup is stopped.', [
                'source_id'       => $sourceId,
                'organization_id' => $organizationId,
                'deletion_run_id' => $deletionRunId,
                'exception'       => $throwable,
            ]);

            throw new SourceDeletionExecutionLeaseLostException(
                'The source deletion execution lease connection was lost.',
                0,
                $throwable,
            );
        }
    }

    public function commit(string $sourceId, string $organizationId, string $deletionRunId): void
    {
        $this->assertOwned($sourceId, $organizationId, $deletionRunId);

        try {
            $this->assertHeld($sourceId, $organizationId, $deletionRunId);
            $this->leaseWriteDriver()->commitTransaction();
            $this->mutationWriteDriver()->commitTransaction();
        } catch (Throwable $throwable) {
            $this->logger->critical('Atomic source deletion commit failed; worker restart is required.', [
                'source_id'       => $sourceId,
                'organization_id' => $organizationId,
                'deletion_run_id' => $deletionRunId,
                'exception'       => $throwable,
            ]);

            $this->rollbackOpenTransactions();

            throw $throwable instanceof SourceDeletionExecutionLeaseLostException
                ? $throwable
                : new SourceDeletionExecutionLeaseLostException(
                    'The atomic source deletion boundary could not be committed.',
                    0,
                    $throwable,
                );
        } finally {
            $this->clearOwnership();
        }
    }

    public function rollback(string $sourceId, string $organizationId, string $deletionRunId): void
    {
        if (! $this->isOwnedBy($sourceId, $organizationId, $deletionRunId)) {
            return;
        }

        $this->logger->warning('Rolling back the atomic source deletion boundary.', [
            'source_id'       => $sourceId,
            'organization_id' => $organizationId,
            'deletion_run_id' => $deletionRunId,
        ]);

        $this->rollbackOpenTransactions();
        $this->clearOwnership();
    }

    private function leaseWriteDriver(): DriverInterface
    {
        return $this->databaseProvider->database(self::LEASE_DATABASE)->getDriver(DatabaseInterface::WRITE);
    }

    private function mutationWriteDriver(): DriverInterface
    {
        return $this->databaseProvider->database()->getDriver(DatabaseInterface::WRITE);
    }

    private function isLockGranted(mixed $result): bool
    {
        return true === $result || 1 === $result || '1' === $result || 't' === $result;
    }

    private function rollbackTransaction(DriverInterface $driver): void
    {
        try {
            if (0 < $driver->getTransactionLevel()) {
                $driver->rollbackTransaction();
            }
        } catch (Throwable) {
            $driver->disconnect();
        }
    }

    private function rollbackOpenTransactions(): void
    {
        $this->rollbackTransaction($this->mutationWriteDriver());
        $this->rollbackTransaction($this->leaseWriteDriver());
    }

    private function assertOwned(string $sourceId, string $organizationId, string $deletionRunId): void
    {
        if (! $this->isOwnedBy($sourceId, $organizationId, $deletionRunId)) {
            throw new SourceDeletionExecutionLeaseLostException('The atomic source deletion boundary is not owned by this lifecycle run.');
        }
    }

    private function isOwnedBy(string $sourceId, string $organizationId, string $deletionRunId): bool
    {
        return SourceDeletionExecutionLock::key($sourceId, $organizationId) === $this->heldLockKey
            && $deletionRunId === $this->heldDeletionRunId;
    }

    private function clearOwnership(): void
    {
        $this->heldLockKey       = null;
        $this->heldDeletionRunId = null;
    }
}
