<?php

declare(strict_types=1);

namespace Packio\Webhook\Application\Command;

use Packio\Webhook\Application\Dto\SourceData;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;

final readonly class WebhookCommandService
{
    public function __construct(
        private WebhookEventRepositoryInterface $webhookEventRepository,
    ) {}

    public function findDelivery(string $sourceId, string $deliveryId): ?WebhookEvent
    {
        return $this->webhookEventRepository->findBySourceIdAndDeliveryId($sourceId, $deliveryId);
    }

    public function receiveDelivery(
        string $sourceId,
        string $provider,
        string $deliveryId,
        string $event,
        string $payloadHash,
        bool $active
    ): WebhookEvent {
        $webhookEvent = WebhookEvent::receive($sourceId, $provider, $deliveryId, $event, $payloadHash);
        if (! $active) {
            $webhookEvent->markIgnored('Source is inactive.');
        }

        try {
            return $this->webhookEventRepository->save($webhookEvent);
        } catch (RepositoryPersistException $exception) {
            // A concurrent request saved the same delivery first (unique source/provider/delivery index).
            return $this->webhookEventRepository->findBySourceIdAndDeliveryId($sourceId, $deliveryId) ?? throw $exception;
        }
    }

    public function handleVerifiedEvent(WebhookEvent $webhookEvent, WebhookInspectionResult $webhookInspectionResult): WebhookEvent
    {
        match ($webhookInspectionResult->status) {
            'processed' => $webhookEvent->markProcessed($webhookInspectionResult->message),
            'ignored'   => $webhookEvent->markIgnored($webhookInspectionResult->message),
            default     => $webhookEvent->markRejected($webhookInspectionResult->message),
        };

        return $this->webhookEventRepository->save($webhookEvent);
    }

    public function deleteEventsForSource(string $sourceId): int
    {
        return $this->webhookEventRepository->deleteBySourceId($sourceId);
    }

    public function markSyncScheduled(WebhookEvent $webhookEvent, string $jobId, string $message, bool $reprocess = false): WebhookEvent
    {
        $webhookEvent->markSyncScheduled($jobId, $message, $reprocess);

        return $this->webhookEventRepository->save($webhookEvent);
    }

    public function markSyncScheduleFailed(WebhookEvent $webhookEvent, string $message, bool $reprocess = false): WebhookEvent
    {
        $webhookEvent->markSyncScheduleFailed($message, $reprocess);

        return $this->webhookEventRepository->save($webhookEvent);
    }

    public function reprocessDelivery(SourceData $sourceData, string $deliveryId): ?WebhookEvent
    {
        if (! $sourceData->active) {
            return null;
        }

        return $this->webhookEventRepository->findBySourceIdAndDeliveryId($sourceData->id, $deliveryId);
    }
}
