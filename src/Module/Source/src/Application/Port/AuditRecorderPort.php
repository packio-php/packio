<?php

declare(strict_types=1);

namespace Packio\Source\Application\Port;

interface AuditRecorderPort
{
    /**
     * @param array<string, mixed> $context
     */
    public function recordOnce(
        string $operationKey,
        string $event,
        array $context,
        string $organizationId,
        string $sourceId,
        string $actorId,
        string $subjectType,
        string $subjectId,
    ): bool;
}
