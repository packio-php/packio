<?php

declare(strict_types=1);

namespace PackioTest\Unit\Webhook\Infrastructure\Webhook;

use Cake\Chronos\Chronos;
use Packio\Webhook\Application\Command\WebhookCommandService;
use Packio\Webhook\Application\Dto\SourceData;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;
use Packio\Webhook\Infrastructure\Webhook\CodebergWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\InvalidWebhookRequest;
use Packio\Webhook\Infrastructure\Webhook\WebhookDispatcher;
use Packio\Webhook\Infrastructure\Webhook\WebhookProviderHandlerConfig;
use Packio\Webhook\Infrastructure\Webhook\WebhookProviderHandlerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function hash_hmac;
use function json_encode;

final class CodebergWebhookDispatcherTest extends TestCase
{
    #[Test]
    public function codebergPushWebhookIsProcessedWhenSignatureAndRepositoryMatch(): void
    {
        $payload = json_encode([
            'repository' => [
                'full_name' => 'owner/repo',
                'html_url'  => 'https://codeberg.org/owner/repo',
            ],
        ], JSON_THROW_ON_ERROR);
        $secret = 'webhook-secret';

        $event = $this->service()->handleWebhook(
            new SourceData(id: 'source-01', organizationId: '', provider: 'codeberg', url: 'https://codeberg.org/owner/repo', active: true, webhookSecret: $secret),
            [
                'x-forgejo-event'     => 'push',
                'x-forgejo-delivery'  => 'delivery-01',
                'x-forgejo-signature' => hash_hmac('sha256', $payload, $secret),
            ],
            $payload,
        );

        self::assertInstanceOf(WebhookEvent::class, $event);
        self::assertSame('codeberg', $event->provider());
        self::assertSame('delivery-01', $event->deliveryId());
        self::assertSame('push', $event->event());
        self::assertSame('processed', $event->status());
        self::assertSame('Sync scheduled for https://codeberg.org/owner/repo.', $event->message());
    }

    #[Test]
    public function codebergWebhookRejectsInvalidSignature(): void
    {
        $this->expectException(InvalidWebhookRequest::class);
        $this->expectExceptionMessage('Invalid Codeberg signature.');
        $payload = json_encode([
            'repository' => [
                'html_url' => 'https://codeberg.org/owner/repo',
            ],
        ], JSON_THROW_ON_ERROR);

        $this->service()->handleWebhook(
            new SourceData(id: 'source-01', organizationId: '', provider: 'codeberg', url: 'https://codeberg.org/owner/repo', active: true, webhookSecret: 'webhook-secret'),
            [
                'x-forgejo-event'     => 'push',
                'x-forgejo-delivery'  => 'delivery-01',
                'x-forgejo-signature' => 'invalid',
            ],
            $payload,
        );
    }

    private function service(): WebhookDispatcher
    {
        return new WebhookDispatcher(
            new WebhookCommandService(
                new class implements WebhookEventRepositoryInterface {
                    /** @var array<string, WebhookEvent> */
                    private array $events = [];

                    public function save(WebhookEvent $webhookEvent): WebhookEvent
                    {
                        $this->events[$webhookEvent->sourceId() . ':' . $webhookEvent->deliveryId()] = $webhookEvent;

                        return $webhookEvent;
                    }

                    public function countOlderThan(Chronos $before): int
                    {
                        return 0;
                    }

                    public function deleteOlderThan(Chronos $before, int $batchSize = 1000): int
                    {
                        return 0;
                    }

                    public function deleteBySourceId(string $sourceId): int
                    {
                        return 0;
                    }

                    public function findBySourceIdAndDeliveryId(string $sourceId, string $deliveryId): ?WebhookEvent
                    {
                        return $this->events[$sourceId . ':' . $deliveryId] ?? null;
                    }

                    public function recentForSource(string $sourceId, int $limit = 10): array
                    {
                        return [];
                    }

                    public function recentForSources(array $sourceIds, int $limit): array
                    {
                        return [];
                    }
                }
            ),
            new WebhookProviderHandlerRegistry([
                new CodebergWebhookProviderHandler(new WebhookProviderHandlerConfig('codeberg', ['push', 'create', 'delete'])),
            ]),
        );
    }
}
