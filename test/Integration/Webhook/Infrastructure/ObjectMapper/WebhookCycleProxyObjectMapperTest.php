<?php

declare(strict_types=1);

namespace PackioTest\Integration\Webhook\Infrastructure\ObjectMapper;

use Cycle\ORM\EntityProxyInterface;
use Packio\Webhook\Api\Dto\WebhookEventDto;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

final class WebhookCycleProxyObjectMapperTest extends IntegrationTestCase
{
    #[Test]
    public function itMapsPersistedAndReloadedWebhookCycleProxiesThroughEveryFacadeListCall(): void
    {
        $webhookEvents = $this->webhookEvents();
        $sourceId      = 'object-mapper-source';

        $webhookEvent = WebhookEvent::receive($sourceId, 'github', 'received-delivery', 'push', 'payload-hash');
        $webhookEvents->save($webhookEvent);

        $processed = WebhookEvent::receive($sourceId, 'github', 'processed-delivery', 'push', 'payload-hash');
        $processed->markSyncScheduled('scheduled-job', 'Sync scheduled.');
        $webhookEvents->save($processed);

        $rejected = WebhookEvent::receive($sourceId, 'github', 'rejected-delivery', 'push', 'payload-hash');
        $rejected->markRejected('Signature does not match.');
        $webhookEvents->save($rejected);

        $this->rebuildContainer();

        $reloadedEvents = $this->webhookEvents()->recentForSource($sourceId, 10);
        self::assertCount(3, $reloadedEvents);
        foreach ($reloadedEvents as $reloadedEvent) {
            self::assertInstanceOf(EntityProxyInterface::class, $reloadedEvent);
        }

        $webhookApiFacade = $this->getService(WebhookApiFacadeInterface::class);
        self::assertInstanceOf(WebhookApiFacadeInterface::class, $webhookApiFacade);

        $recentEvents = $webhookApiFacade->recentEvents($sourceId, 10);
        self::assertCount(3, $recentEvents);

        $webhookEventDto = $this->dtoByDeliveryId($recentEvents, 'received-delivery');
        self::assertSame('received', $webhookEventDto->status);
        self::assertNull($webhookEventDto->processedAt);
        self::assertNull($webhookEventDto->scheduledJobId);
        self::assertSame(0, $webhookEventDto->reprocessCount);

        $processedDto = $this->dtoByDeliveryId($recentEvents, 'processed-delivery');
        self::assertSame('processed', $processedDto->status);
        self::assertNotNull($processedDto->processedAt);
        self::assertSame('scheduled-job', $processedDto->scheduledJobId);
        self::assertSame(0, $processedDto->reprocessCount);

        $rejectedDto = $this->dtoByDeliveryId($recentEvents, 'rejected-delivery');
        self::assertSame('rejected', $rejectedDto->status);
        self::assertNotNull($rejectedDto->processedAt);
    }

    private function webhookEvents(): WebhookEventRepositoryInterface
    {
        $webhookEvents = $this->getService(WebhookEventRepositoryInterface::class);
        self::assertInstanceOf(WebhookEventRepositoryInterface::class, $webhookEvents);

        return $webhookEvents;
    }

    /** @param list<WebhookEventDto> $events */
    private function dtoByDeliveryId(array $events, string $deliveryId): WebhookEventDto
    {
        foreach ($events as $event) {
            if ($deliveryId === $event->deliveryId) {
                return $event;
            }
        }

        self::fail('Expected webhook event DTO was not returned.');
    }
}
