<?php

declare(strict_types=1);

namespace Packio\Webhook\Domain\Entity;

use Cake\Chronos\Chronos;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

class WebhookEvent implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    public function __construct(
        private readonly string $sourceId = '',
        private readonly string $provider = '',
        private readonly string $deliveryId = '',
        private readonly string $event = '',
        private string $status = 'received',
        private readonly string $payloadHash = '',
        private ?Chronos $processedAt = null,
        private string $message = '',
        private ?string $scheduledJobId = null,
        private int $reprocessCount = 0,
    ) {}

    public static function receive(
        string $sourceId,
        string $provider,
        string $deliveryId,
        string $event,
        string $payloadHash,
    ): self {
        return new self(
            sourceId: $sourceId,
            provider: $provider,
            deliveryId: $deliveryId,
            event: $event,
            status: 'received',
            payloadHash: $payloadHash,
        );
    }

    public function markProcessed(string $message): void
    {
        $this->status      = 'processed';
        $this->processedAt = new Chronos();
        $this->message     = $message;
    }

    public function markSyncScheduled(string $scheduledJobId, string $message, bool $reprocess = false): void
    {
        $this->status         = 'processed';
        $this->processedAt    = new Chronos();
        $this->scheduledJobId = $scheduledJobId;
        $this->message        = $message;
        if ($reprocess) {
            ++$this->reprocessCount;
        }
    }

    public function markSyncScheduleFailed(string $message, bool $reprocess = false): void
    {
        $this->status      = 'sync_schedule_failed';
        $this->processedAt = new Chronos();
        $this->message     = $message;
        if ($reprocess) {
            ++$this->reprocessCount;
        }
    }

    public function markIgnored(string $message): void
    {
        $this->status      = 'ignored';
        $this->processedAt = new Chronos();
        $this->message     = $message;
    }

    public function markRejected(string $message): void
    {
        $this->status      = 'rejected';
        $this->processedAt = new Chronos();
        $this->message     = $message;
    }

    public function sourceId(): string
    {
        return $this->sourceId;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function deliveryId(): string
    {
        return $this->deliveryId;
    }

    public function event(): string
    {
        return $this->event;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function payloadHash(): string
    {
        return $this->payloadHash;
    }

    public function processedAt(): ?Chronos
    {
        return $this->processedAt;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function scheduledJobId(): ?string
    {
        return $this->scheduledJobId;
    }

    public function reprocessCount(): int
    {
        return $this->reprocessCount;
    }
}
