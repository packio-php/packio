<?php

declare(strict_types=1);

namespace Packio\Webhook\Domain\Repository;

use Cake\Chronos\Chronos;
use Packio\Webhook\Domain\Entity\WebhookEvent;

interface WebhookEventRepositoryInterface
{
    public function save(WebhookEvent $webhookEvent): WebhookEvent;

    public function deleteBySourceId(string $sourceId): int;

    public function countOlderThan(Chronos $before): int;

    /**
     * Deletes events created before $before in batches of $batchSize, oldest first.
     */
    public function deleteOlderThan(Chronos $before, int $batchSize = 1000): int;

    public function findBySourceIdAndDeliveryId(string $sourceId, string $deliveryId): ?WebhookEvent;

    /**
     * @return list<WebhookEvent>
     */
    public function recentForSource(string $sourceId, int $limit = 10): array;

    /**
     * Up to $limit newest events per source, newest first.
     *
     * @param list<string> $sourceIds
     *
     * @return list<WebhookEvent>
     */
    public function recentForSources(array $sourceIds, int $limit): array;
}
