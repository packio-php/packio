<?php

declare(strict_types=1);

namespace Packio\Audit\Infrastructure\Facade;

use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Audit\Api\Dto\AuditEntryDto;
use Packio\Audit\Application\Command\AuditCommandService;
use Packio\Audit\Application\Query\AuditQueryService;
use Packio\Audit\Domain\Entity\AuditEntry;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function array_map;

final readonly class AuditApiFacade implements AuditApiFacadeInterface
{
    public function __construct(
        private AuditCommandService $auditCommandService,
        private AuditQueryService $auditQueryService,
        private ObjectMapperInterface $objectMapper,
    ) {}

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
        $this->auditCommandService->record(
            $event,
            $context,
            $organizationId,
            $sourceId,
            $packageName,
            $actorId,
            $subjectType,
            $subjectId,
        );
    }

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
        return $this->auditCommandService->recordOnce(
            $operationKey,
            $event,
            $context,
            $organizationId,
            $sourceId,
            $packageName,
            $actorId,
            $subjectType,
            $subjectId,
        );
    }

    /**
     * @return list<AuditEntryDto>
     */
    public function recentByOrganization(string $organizationId, int $limit = 10): array
    {
        return array_map(
            fn (AuditEntry $auditEntry): AuditEntryDto => $this->objectMapper->map($auditEntry, AuditEntryDto::class),
            $this->auditQueryService->recentByOrganization($organizationId, $limit),
        );
    }

    /**
     * @return list<AuditEntryDto>
     */
    public function recentBySource(string $organizationId, string $sourceId, int $limit = 10): array
    {
        return array_map(
            fn (AuditEntry $auditEntry): AuditEntryDto => $this->objectMapper->map($auditEntry, AuditEntryDto::class),
            $this->auditQueryService->recentBySource($organizationId, $sourceId, $limit),
        );
    }

    /**
     * @return list<AuditEntryDto>
     */
    public function recentBySubject(string $subjectType, string $subjectId, int $limit = 10): array
    {
        return array_map(
            fn (AuditEntry $auditEntry): AuditEntryDto => $this->objectMapper->map($auditEntry, AuditEntryDto::class),
            $this->auditQueryService->recentBySubject($subjectType, $subjectId, $limit),
        );
    }

    public function recentBySubjectAndEvents(string $subjectType, string $subjectId, array $events, int $limit = 10): array
    {
        return array_map(
            fn (AuditEntry $auditEntry): AuditEntryDto => $this->objectMapper->map($auditEntry, AuditEntryDto::class),
            $this->auditQueryService->recentBySubjectAndEvents($subjectType, $subjectId, $events, $limit),
        );
    }
}
