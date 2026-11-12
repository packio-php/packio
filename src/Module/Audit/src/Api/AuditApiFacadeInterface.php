<?php

declare(strict_types=1);

namespace Packio\Audit\Api;

use Packio\Audit\Api\Dto\AuditEntryDto;

interface AuditApiFacadeInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function record(
        string $event,
        array $context,
        string $organizationId = '',
        string $sourceId = '',
        string $packageName = '',
        string $actorId = '',
        string $subjectType = '',
        string $subjectId = '',
    ): void;

    /**
     * Records an audit entry at most once for a durable lifecycle operation.
     *
     * @param array<string, mixed> $context
     */
    public function recordOnce(
        string $operationKey,
        string $event,
        array $context,
        string $organizationId = '',
        string $sourceId = '',
        string $packageName = '',
        string $actorId = '',
        string $subjectType = '',
        string $subjectId = '',
    ): bool;

    /**
     * @return list<AuditEntryDto>
     */
    public function recentByOrganization(string $organizationId, int $limit = 10): array;

    /**
     * @return list<AuditEntryDto>
     */
    public function recentBySource(string $organizationId, string $sourceId, int $limit = 10): array;

    /**
     * @return list<AuditEntryDto>
     */
    public function recentBySubject(string $subjectType, string $subjectId, int $limit = 10): array;

    /**
     * @param non-empty-list<string> $events
     *
     * @return list<AuditEntryDto>
     */
    public function recentBySubjectAndEvents(string $subjectType, string $subjectId, array $events, int $limit = 10): array;
}
