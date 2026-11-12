<?php

declare(strict_types=1);

namespace Packio\Worker\Domain\Entity;

use Cake\Chronos\Chronos;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

class Job implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELED  = 'canceled';
    public const STATUS_HELD      = 'held';
    public const STATUS_AUDITED   = 'audited';

    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly string $organizationId = '',
        private readonly string $type = '',
        private readonly array $payload = [],
        private readonly ?string $idempotencyKey = null,
        private string $status = self::STATUS_PENDING,
        private readonly ?int $priority = 0,
        private ?Chronos $scheduledAt = null,
        private ?Chronos $startedAt = null,
        private ?Chronos $completedAt = null,
        private int $attemptCount = 0,
        private readonly int $maxAttempts = 3,
        private ?string $errorMessage = null,
        private ?string $errorDetails = null,
        private ?string $workerId = null,
    ) {
        $this->scheduledAt ??= new Chronos();
    }

    /** @param array<string, mixed> $payload */
    public static function schedule(
        string $organizationId,
        string $type,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
        ?string $idempotencyKey = null
    ): self {
        return new self(organizationId: $organizationId, type: $type, payload: $payload, idempotencyKey: $idempotencyKey, priority: $priority, scheduledAt: $scheduledAt, maxAttempts: $maxAttempts);
    }

    /** @param array<string, mixed> $payload */
    public static function hold(
        string $organizationId,
        string $type,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
        ?string $idempotencyKey = null,
    ): self {
        return new self(
            organizationId: $organizationId,
            type: $type,
            payload: $payload,
            idempotencyKey: $idempotencyKey,
            status: self::STATUS_HELD,
            priority: $priority,
            scheduledAt: $scheduledAt,
            maxAttempts: $maxAttempts,
        );
    }

    public function publish(): void
    {
        $this->status = self::STATUS_PENDING;
    }

    public function confirmAudit(): void
    {
        $this->status = self::STATUS_AUDITED;
    }

    public function markRunning(string $workerId): void
    {
        $this->status    = self::STATUS_RUNNING;
        $this->workerId  = $workerId;
        $this->startedAt = new Chronos();
        ++$this->attemptCount;
    }

    public function markCompleted(): void
    {
        $this->status       = self::STATUS_COMPLETED;
        $this->completedAt  = new Chronos();
        $this->errorMessage = null;
        $this->errorDetails = null;
    }

    public function markFailed(string $message, ?string $details = null): void
    {
        $this->status       = self::STATUS_FAILED;
        $this->errorMessage = $message;
        $this->errorDetails = $details;
        $this->completedAt  = new Chronos();
    }

    public function markPendingForRetry(): void
    {
        $this->status      = self::STATUS_PENDING;
        $this->workerId    = null;
        $this->startedAt   = null;
        $delaySeconds      = (int) 5 ** $this->attemptCount;
        $this->scheduledAt = new Chronos("+{$delaySeconds} seconds");
    }

    public function markPendingForInfrastructureRetry(): void
    {
        $this->status       = self::STATUS_PENDING;
        $this->workerId     = null;
        $this->startedAt    = null;
        $this->completedAt  = null;
        $this->errorMessage = null;
        $this->errorDetails = null;
        $this->scheduledAt  = new Chronos('+5 seconds');
    }

    public function markCanceled(): void
    {
        $this->status      = self::STATUS_CANCELED;
        $this->completedAt = new Chronos();
    }

    public function canRetry(): bool
    {
        return $this->attemptCount < $this->maxAttempts;
    }

    public function isPending(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }

    public function isRunning(): bool
    {
        return self::STATUS_RUNNING === $this->status;
    }

    public function isHeld(): bool
    {
        return self::STATUS_HELD === $this->status;
    }

    public function isAudited(): bool
    {
        return self::STATUS_AUDITED === $this->status;
    }

    public function isCancelable(): bool
    {
        return $this->isPending() || $this->isHeld() || $this->isAudited();
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function type(): string
    {
        return $this->type;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function priority(): int
    {
        return $this->priority ?? 0;
    }

    public function attemptCount(): int
    {
        return $this->attemptCount;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function scheduledAt(): ?Chronos
    {
        return $this->scheduledAt;
    }

    public function startedAt(): ?Chronos
    {
        return $this->startedAt;
    }

    public function completedAt(): ?Chronos
    {
        return $this->completedAt;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function errorDetails(): ?string
    {
        return $this->errorDetails;
    }

    public function workerId(): ?string
    {
        return $this->workerId;
    }

    public function durationSeconds(): ?int
    {
        if (! $this->startedAt || ! $this->completedAt) {
            return null;
        }

        return $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
    }
}
