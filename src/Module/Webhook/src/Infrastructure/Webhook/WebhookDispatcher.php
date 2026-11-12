<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Webhook;

use Packio\Webhook\Application\Command\WebhookCommandService;
use Packio\Webhook\Application\Dto\SourceData;
use Packio\Webhook\Domain\Entity\WebhookEvent;

use function sha1;
use function strlen;

final readonly class WebhookDispatcher
{
    public function __construct(
        private WebhookCommandService $webhookCommandService,
        private WebhookProviderHandlerRegistry $webhookProviderHandlerRegistry,
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public function handleWebhook(SourceData $sourceData, array $headers, string $payload): ?WebhookEvent
    {
        $handler = $this->webhookProviderHandlerRegistry->handler($sourceData->provider);
        if (! $handler instanceof WebhookProviderHandlerInterface) {
            return null;
        }

        // Bound untrusted metadata before hashing, authentication or persistence.
        foreach ($headers as $name => $value) {
            if (strlen($name) > 128 || strlen($value) > 8192) {
                throw new InvalidWebhookRequest('Webhook headers are too long.', 400);
            }
        }
        $envelope = $handler->envelope($sourceData, $headers, $payload);
        if (strlen($envelope->deliveryId) > 255 || strlen($envelope->event) > 255) {
            throw new InvalidWebhookRequest('Webhook delivery ID or event is too long.', 400);
        }
        $error = $handler->validateSignature($sourceData, $envelope, $payload);
        if (null !== $error) {
            throw new InvalidWebhookRequest($error);
        }

        $sourceId = $sourceData->id;
        $existing = $this->webhookCommandService->findDelivery($sourceId, $envelope->deliveryId);
        if ($existing instanceof WebhookEvent) {
            return $existing;
        }

        $active        = $sourceData->active;
        $webhookEvent  = $this->webhookCommandService->receiveDelivery(
            $sourceId,
            $envelope->provider,
            $envelope->deliveryId,
            $envelope->event,
            sha1($payload),
            $active,
        );
        // Not "received": the delivery was already handled, so answer as a duplicate. A re-read event may still be
        // "received" (a prior attempt did not finish) and is then inspected again.
        if (! $active || 'received' !== $webhookEvent->status()) {
            return $webhookEvent;
        }

        return $this->webhookCommandService->handleVerifiedEvent(
            $webhookEvent,
            $handler->inspectPayload($sourceData, $envelope, $payload),
        );
    }

    public function reprocessDelivery(SourceData $sourceData, string $deliveryId): ?WebhookEvent
    {
        if (! $this->webhookProviderHandlerRegistry->handler($sourceData->provider) instanceof WebhookProviderHandlerInterface) {
            return null;
        }

        return $this->webhookCommandService->reprocessDelivery($sourceData, $deliveryId);
    }
}
