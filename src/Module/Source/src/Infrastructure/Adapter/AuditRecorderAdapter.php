<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter;

use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Source\Application\Port\AuditRecorderPort;

final readonly class AuditRecorderAdapter implements AuditRecorderPort
{
    public function __construct(private AuditApiFacadeInterface $auditApiFacade) {}

    public function recordOnce(
        string $operationKey,
        string $event,
        array $context,
        string $organizationId,
        string $sourceId,
        string $actorId,
        string $subjectType,
        string $subjectId,
    ): bool {
        return $this->auditApiFacade->recordOnce(
            $operationKey,
            $event,
            $context,
            organizationId: $organizationId,
            sourceId: $sourceId,
            packageName: '',
            actorId: $actorId,
            subjectType: $subjectType,
            subjectId: $subjectId,
        );
    }
}
