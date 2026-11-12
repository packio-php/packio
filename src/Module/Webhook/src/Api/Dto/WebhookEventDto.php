<?php

declare(strict_types=1);

namespace Packio\Webhook\Api\Dto;

final readonly class WebhookEventDto
{
    public function __construct(
        public string $sourceId,
        public string $provider,
        public string $deliveryId,
        public string $event,
        public string $status,
        public string $message,
        public string $createdAt,
        public ?string $processedAt = null,
        public ?string $scheduledJobId = null,
        public int $reprocessCount = 0,
    ) {}
}
