<?php

declare(strict_types=1);

namespace Packio\Audit\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\Injection\Fragment;
use InvalidArgumentException;
use Packio\Audit\Domain\Entity\AuditEntry;
use Packio\Audit\Domain\Repository\AuditRepositoryInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

use function iterator_to_array;
use function max;

/**
 * @extends AbstractWriteRepository<AuditEntry>
 */
final class AuditRepository extends AbstractWriteRepository implements AuditRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string ENTITY_CLASS = AuditEntry::class;

    public function save(AuditEntry $auditEntry): AuditEntry
    {
        $this->persistAndFlush($auditEntry);

        return $auditEntry;
    }

    public function saveOnce(AuditEntry $auditEntry): bool
    {
        $operationKey = $auditEntry->operationKey();
        if (null === $operationKey || '' === $operationKey) {
            throw new InvalidArgumentException('Idempotent audit entries require an operation key.');
        }

        try {
            $this->persistAndFlush($auditEntry);
        } catch (RepositoryPersistException $exception) {
            if ($this->findByOperationKey($operationKey) instanceof AuditEntry) {
                return false;
            }

            throw $exception;
        }

        return true;
    }

    public function recentByOrganization(string $organizationId, int $limit = 10): array
    {
        return iterator_to_array(
            $this->select()
                ->where([
                    'organizationId' => $organizationId,
                ])
                ->orderBy([
                    'occurredAt' => 'DESC',
                    'uuid'       => 'DESC',
                ])
                ->limit($limit)
                ->fetchAll(),
            false,
        );
    }

    public function recentBySource(string $organizationId, string $sourceId, int $limit = 10): array
    {
        return iterator_to_array(
            $this->select()
                ->where([
                    'organizationId' => $organizationId,
                    'sourceId'       => $sourceId,
                ])
                ->orderBy([
                    'occurredAt' => 'DESC',
                    'uuid'       => 'DESC',
                ])
                ->limit($limit)
                ->fetchAll(),
            false,
        );
    }

    public function recentBySubject(string $subjectType, string $subjectId, int $limit = 10): array
    {
        return iterator_to_array(
            $this->select()
                ->where([
                    'subjectType' => $subjectType,
                    'subjectId'   => $subjectId,
                ])
                ->orderBy([
                    'occurredAt' => 'DESC',
                    'uuid'       => 'DESC',
                ])
                ->limit($limit)
                ->fetchAll(),
            false,
        );
    }

    public function recentBySubjectAndEvents(string $subjectType, string $subjectId, array $events, int $limit = 10): array
    {
        return iterator_to_array(
            $this->select()
                ->where([
                    'subjectType' => $subjectType,
                    'subjectId'   => $subjectId,
                ])
                ->where('event', 'in', $events)
                ->orderBy([
                    'occurredAt' => 'DESC',
                    'uuid'       => 'DESC',
                ])
                ->limit($limit)
                ->fetchAll(),
            false,
        );
    }

    public function countOlderThan(Chronos $before): int
    {
        return $this->database()->select()->from('audit_entries')
            ->where('occurred_at', '<', $before->setTimezone('UTC')->toDateTimeString())
            ->count()
        ;
    }

    public function deleteOlderThan(Chronos $before, int $batchSize = 1000): int
    {
        $total     = 0;
        $batchSize = max(1, $batchSize);

        do {
            // Postgres has no DELETE ... LIMIT, so pick the batch by key.
            $deleted = $this->database()->delete('audit_entries')
                ->where(new Fragment(
                    'uuid IN (SELECT uuid FROM audit_entries WHERE occurred_at < ? ORDER BY occurred_at LIMIT ?)',
                    $before->setTimezone('UTC')->toDateTimeString(),
                    $batchSize,
                ))
                ->run()
            ;
            $total += $deleted;
        } while ($deleted >= $batchSize);

        return $total;
    }

    private function findByOperationKey(string $operationKey): ?AuditEntry
    {
        $auditEntry = $this->select()->where([
            'operationKey' => $operationKey,
        ])->fetchOne();

        return $auditEntry instanceof AuditEntry ? $auditEntry : null;
    }
}
