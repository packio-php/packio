<?php

declare(strict_types=1);

namespace Packio\Audit\Application\Command;

use Packio\Audit\Domain\Entity\AuditEntry;
use Packio\Audit\Domain\Repository\AuditRepositoryInterface;

final readonly class AuditCommandService
{
    public function __construct(private AuditRepositoryInterface $auditRepository) {}

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
    ): void {
        $this->auditRepository->save(
            AuditEntry::record(
                $event,
                $context,
                $organizationId,
                $sourceId,
                $packageName,
                $actorId,
                $subjectType,
                $subjectId,
            ),
        );
    }

    /**
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
    ): bool {
        return $this->auditRepository->saveOnce(
            AuditEntry::record(
                $event,
                $context,
                $organizationId,
                $sourceId,
                $packageName,
                $actorId,
                $subjectType,
                $subjectId,
                $operationKey,
            ),
        );
    }
}
