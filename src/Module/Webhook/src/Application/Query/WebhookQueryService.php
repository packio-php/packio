<?php

declare(strict_types=1);

namespace Packio\Webhook\Application\Query;

use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;

final readonly class WebhookQueryService
{
    public function __construct(private WebhookEventRepositoryInterface $webhookEventRepository) {}

    /**
     * @return list<WebhookEvent>
     */
    public function recentEvents(string $sourceId, int $limit = 5): array
    {
        return $this->webhookEventRepository->recentForSource($sourceId, $limit);
    }

    /**
     * @param list<string> $sourceIds
     *
     * @return list<WebhookEvent>
     */
    public function recentEventsForSources(array $sourceIds, int $limit): array
    {
        return $this->webhookEventRepository->recentForSources($sourceIds, $limit);
    }
}
