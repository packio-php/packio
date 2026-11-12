<?php

declare(strict_types=1);

namespace Packio\Audit\Application\Query;

use Packio\Audit\Domain\Entity\AuditEntry;
use Packio\Audit\Domain\Repository\AuditRepositoryInterface;

final readonly class AuditQueryService
{
    public function __construct(private AuditRepositoryInterface $auditRepository) {}

    /**
     * @return list<AuditEntry>
     */
    public function recentByOrganization(string $organizationId, int $limit = 10): array
    {
        return $this->auditRepository->recentByOrganization($organizationId, $limit);
    }

    /**
     * @return list<AuditEntry>
     */
    public function recentBySource(string $organizationId, string $sourceId, int $limit = 10): array
    {
        return $this->auditRepository->recentBySource($organizationId, $sourceId, $limit);
    }

    /**
     * @return list<AuditEntry>
     */
    public function recentBySubject(string $subjectType, string $subjectId, int $limit = 10): array
    {
        return $this->auditRepository->recentBySubject($subjectType, $subjectId, $limit);
    }

    /**
     * @param non-empty-list<string> $events
     *
     * @return list<AuditEntry>
     */
    public function recentBySubjectAndEvents(string $subjectType, string $subjectId, array $events, int $limit = 10): array
    {
        return $this->auditRepository->recentBySubjectAndEvents($subjectType, $subjectId, $events, $limit);
    }
}
