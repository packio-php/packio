<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Webhook\Api\Dto\WebhookEventDto;

final readonly class WebhookEventResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(WebhookEventDto $webhookEventDto): array
    {
        return [
            'source_id'        => $webhookEventDto->sourceId,
            'provider'         => $webhookEventDto->provider,
            'delivery_id'      => $webhookEventDto->deliveryId,
            'event'            => $webhookEventDto->event,
            'status'           => $webhookEventDto->status,
            'message'          => '' !== $webhookEventDto->message ? $webhookEventDto->message : null,
            'created_at'       => $webhookEventDto->createdAt,
            'processed_at'     => $webhookEventDto->processedAt,
            'scheduled_job_id' => $webhookEventDto->scheduledJobId,
            'reprocess_count'  => $webhookEventDto->reprocessCount,
        ];
    }
}
