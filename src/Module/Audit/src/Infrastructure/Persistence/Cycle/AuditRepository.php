<?php

declare(strict_types=1);

namespace Packio\Audit\Infrastructure\Persistence\Cycle;

use InvalidArgumentException;
use Packio\Audit\Domain\Entity\AuditEntry;
use Packio\Audit\Domain\Repository\AuditRepositoryInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

use function iterator_to_array;

/**
 * @extends AbstractWriteRepository<AuditEntry>
 */
final class AuditRepository extends AbstractWriteRepository implements AuditRepositoryInterface
{
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

    private function findByOperationKey(string $operationKey): ?AuditEntry
    {
        $auditEntry = $this->select()->where([
            'operationKey' => $operationKey,
        ])->fetchOne();

        return $auditEntry instanceof AuditEntry ? $auditEntry : null;
    }
}
