<?php

declare(strict_types=1);

namespace PackioTest\Integration\Webhook\Infrastructure\Persistence;

use Cycle\Database\DatabaseProviderInterface;
use Packio\Webhook\Api\Dto\WebhookEventDto;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_keys;
use function array_map;

final class WebhookEventRepositoryTest extends IntegrationTestCase
{
    #[Test]
    public function itReturnsLatestEventsPerSourceInOneQuery(): void
    {
        $this->saveEvent('source-a', 'a-1', '2026-05-01 10:00:00');
        $this->saveEvent('source-a', 'a-4', '2026-05-01 10:03:00');
        $this->saveEvent('source-a', 'a-2', '2026-05-01 10:01:00');
        $this->saveEvent('source-a', 'a-3', '2026-05-01 10:02:00');
        $this->saveEvent('source-b', 'b-1', '2026-05-01 09:00:00');
        $this->saveEvent('source-d', 'd-1', '2026-05-01 11:00:00');

        $this->rebuildContainer();

        $events = $this->facade()->recentEventsForSources(['source-a', 'source-b', 'source-c'], 3);

        self::assertSame(['source-a', 'source-b'], array_keys($events));
        self::assertSame(['a-4', 'a-3', 'a-2'], $this->deliveryIds($events['source-a']));
        self::assertSame(['b-1'], $this->deliveryIds($events['source-b']));
    }

    #[Test]
    public function itReturnsEmptyArrayForNoSources(): void
    {
        self::assertSame([], $this->facade()->recentEventsForSources([], 3));
    }

    private function saveEvent(string $sourceId, string $deliveryId, string $createdAt): void
    {
        $webhookEvents = $this->getService(WebhookEventRepositoryInterface::class);
        self::assertInstanceOf(WebhookEventRepositoryInterface::class, $webhookEvents);

        $webhookEvent = $webhookEvents->save(WebhookEvent::receive($sourceId, 'github', $deliveryId, 'push', 'payload-hash'));

        // The create listener stamps created_at with the real clock, so set it directly.
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $databaseProvider->database()->update('webhook_events')->values([
            'created_at' => $createdAt,
        ])->where('uuid', $webhookEvent->getIdentifier()->toString())->run();
    }

    private function facade(): WebhookApiFacadeInterface
    {
        $webhookApiFacade = $this->getService(WebhookApiFacadeInterface::class);
        self::assertInstanceOf(WebhookApiFacadeInterface::class, $webhookApiFacade);

        return $webhookApiFacade;
    }

    /**
     * @param list<WebhookEventDto> $events
     *
     * @return list<string>
     */
    private function deliveryIds(array $events): array
    {
        return array_map(static fn (WebhookEventDto $webhookEventDto): string => $webhookEventDto->deliveryId, $events);
    }
}
