<?php

declare(strict_types=1);

namespace Packio\Webhook\Api;

use Packio\Webhook\Api\Dto\WebhookEventDto;
use Packio\Webhook\Api\Dto\WebhookResultDto;

interface WebhookApiFacadeInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function handleWebhook(string $sourceId, array $headers, string $payload, ?string $provider = null): WebhookResultDto;

    /**
     * @return list<WebhookEventDto>
     */
    public function recentEvents(string $sourceId, int $limit = 5): array;

    /**
     * Newest first; sources without events are absent from the result.
     *
     * @param list<string> $sourceIds
     *
     * @return array<string, list<WebhookEventDto>>
     */
    public function recentEventsForSources(array $sourceIds, int $limit): array;

    public function deleteEventsForSource(string $sourceId): int;

    public function reprocessDelivery(string $sourceId, string $deliveryId): WebhookResultDto;
}
