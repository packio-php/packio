<?php

declare(strict_types=1);

namespace Packio\Audit\Domain\Entity;

use Cake\Chronos\Chronos;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

class AuditEntry implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly string $event = '',
        private readonly Chronos $occurredAt = new Chronos(),
        private readonly array $context = [],
        private readonly string $organizationId = '',
        private readonly string $sourceId = '',
        private readonly string $packageName = '',
        private readonly string $actorId = '',
        private readonly string $subjectType = '',
        private readonly string $subjectId = '',
        private readonly ?string $operationKey = null,
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public static function record(
        string $event,
        array $context,
        string $organizationId = '',
        string $sourceId = '',
        string $packageName = '',
        string $actorId = '',
        string $subjectType = '',
        string $subjectId = '',
        ?string $operationKey = null,
    ): self {
        return new self(
            event: $event,
            context: $context,
            organizationId: $organizationId,
            sourceId: $sourceId,
            packageName: $packageName,
            actorId: $actorId,
            subjectType: $subjectType,
            subjectId: $subjectId,
            operationKey: $operationKey,
        );
    }

    public function event(): string
    {
        return $this->event;
    }

    public function occurredAt(): Chronos
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function sourceId(): string
    {
        return $this->sourceId;
    }

    public function packageName(): string
    {
        return $this->packageName;
    }

    public function actorId(): string
    {
        return $this->actorId;
    }

    public function subjectType(): string
    {
        return $this->subjectType;
    }

    public function subjectId(): string
    {
        return $this->subjectId;
    }

    public function operationKey(): ?string
    {
        return $this->operationKey;
    }
}
