<?php

declare(strict_types=1);

namespace Packio\Audit\Api\Dto;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final readonly class AuditEntryDto
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $id,
        public string $event,
        public string $occurredAt,
        public array $context,
        public string $actorId = '',
        public string $subjectType = '',
        public string $subjectId = '',
    ) {
        if (! Uuid::isValid($this->id)) {
            throw new InvalidArgumentException('Audit entry ID must be a valid UUID.');
        }
    }
}
