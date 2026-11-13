<?php

declare(strict_types=1);

namespace Packio\Audit\Domain\Repository;

use Cake\Chronos\Chronos;
use Packio\Audit\Domain\Entity\AuditEntry;

interface AuditRepositoryInterface
{
    public function save(AuditEntry $auditEntry): AuditEntry;

    /** Returns false when the same durable operation was already audited. */
    public function saveOnce(AuditEntry $auditEntry): bool;

    /**
     * @return list<AuditEntry>
     */
    public function recentByOrganization(string $organizationId, int $limit = 10): array;

    /**
     * @return list<AuditEntry>
     */
    public function recentBySource(string $organizationId, string $sourceId, int $limit = 10): array;

    /**
     * @return list<AuditEntry>
     */
    public function recentBySubject(string $subjectType, string $subjectId, int $limit = 10): array;

    /**
     * @param non-empty-list<string> $events
     *
     * @return list<AuditEntry>
     */
    public function recentBySubjectAndEvents(string $subjectType, string $subjectId, array $events, int $limit = 10): array;

    public function countOlderThan(Chronos $before): int;

    /** Deletes in batches and returns the number of deleted entries. */
    public function deleteOlderThan(Chronos $before, int $batchSize = 1000): int;
}
