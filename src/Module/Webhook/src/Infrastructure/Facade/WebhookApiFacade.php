<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Facade;

use Common\Logging\SafeExceptionContext;
use Packio\Webhook\Api\Dto\WebhookEventDto;
use Packio\Webhook\Api\Dto\WebhookResultDto;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Webhook\Application\Command\WebhookCommandService;
use Packio\Webhook\Application\Command\WebhookInspectionResult;
use Packio\Webhook\Application\Dto\SourceData;
use Packio\Webhook\Application\Port\SourcePort;
use Packio\Webhook\Application\Query\WebhookQueryService;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Infrastructure\Webhook\InvalidWebhookRequest;
use Packio\Webhook\Infrastructure\Webhook\WebhookDispatcher;
use Psr\Log\LoggerInterface;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;
use Throwable;

use function array_map;
use function sprintf;

final readonly class WebhookApiFacade implements WebhookApiFacadeInterface
{
    public function __construct(
        private WebhookCommandService $webhookCommandService,
        private WebhookDispatcher $webhookDispatcher,
        private WebhookQueryService $webhookQueryService,
        private SourcePort $sourcePort,
        private ObjectMapperInterface $objectMapper,
        private LoggerInterface $logger,
    ) {}

    public function handleWebhook(string $sourceId, array $headers, string $payload, ?string $provider = null): WebhookResultDto
    {
        $source = $this->sourcePort->findSource($sourceId);
        if (! $source instanceof SourceData) {
            return new WebhookResultDto(404, 'Source not found.');
        }

        if (null !== $provider && $provider !== $source->provider) {
            return new WebhookResultDto(404, 'Source not found.');
        }

        try {
            $event = $this->webhookDispatcher->handleWebhook($source, $headers, $payload);
        } catch (InvalidWebhookRequest $exception) {
            return new WebhookResultDto($exception->statusCode, $exception->getMessage());
        }

        if (! $event instanceof WebhookEvent) {
            return new WebhookResultDto(422, 'Webhook processing is not configured for this provider.', $source->provider);
        }

        if ('processed' === $event->status() || ('sync_schedule_failed' === $event->status() && $source->active)) {
            $event = $this->scheduleSourceSyncForWebhook($source, $event, false);
        }

        return $this->mapEventToResult($event);
    }

    /**
     * @return list<WebhookEventDto>
     */
    public function recentEvents(string $sourceId, int $limit = 5): array
    {
        return array_map(
            fn (WebhookEvent $webhookEvent): WebhookEventDto => $this->objectMapper->map($webhookEvent, WebhookEventDto::class),
            $this->webhookQueryService->recentEvents($sourceId, $limit),
        );
    }

    /**
     * @param list<string> $sourceIds
     *
     * @return array<string, list<WebhookEventDto>>
     */
    public function recentEventsForSources(array $sourceIds, int $limit): array
    {
        $events = [];
        foreach ($this->webhookQueryService->recentEventsForSources($sourceIds, $limit) as $webhookEvent) {
            $events[$webhookEvent->sourceId()][] = $this->objectMapper->map($webhookEvent, WebhookEventDto::class);
        }

        return $events;
    }

    public function deleteEventsForSource(string $sourceId): int
    {
        return $this->webhookCommandService->deleteEventsForSource($sourceId);
    }

    public function reprocessDelivery(string $sourceId, string $deliveryId): WebhookResultDto
    {
        $source = $this->sourcePort->findSource($sourceId);
        if (! $source instanceof SourceData) {
            return new WebhookResultDto(404, 'Source not found.');
        }

        $event = $this->webhookDispatcher->reprocessDelivery($source, $deliveryId);

        if (! $event instanceof WebhookEvent) {
            return new WebhookResultDto(404, 'Webhook delivery not found.', $source->provider);
        }

        $event = $this->scheduleSourceSyncForWebhook($source, $event, true);

        return $this->mapEventToResult($event);
    }

    private function scheduleSourceSyncForWebhook(SourceData $sourceData, WebhookEvent $webhookEvent, bool $reprocess): WebhookEvent
    {
        if (null !== $webhookEvent->scheduledJobId() && ! $reprocess) {
            return $webhookEvent;
        }

        $attempt        = $webhookEvent->reprocessCount() + 1;
        $idempotencyKey = $reprocess
            ? sprintf('webhook-reprocess:%s:%d', $webhookEvent->getIdentifier()->toString(), $attempt)
            : $this->webhookIdempotencyKey($webhookEvent);

        try {
            $jobId = $this->sourcePort->scheduleSourceSyncOnce($sourceData, $idempotencyKey);
        } catch (Throwable $throwable) {
            $this->logger->error('Webhook sync scheduling failed.', [
                'sourceId'   => $sourceData->id,
                'deliveryId' => $webhookEvent->deliveryId(),
            ] + SafeExceptionContext::exception($throwable));

            return $this->webhookCommandService->markSyncScheduleFailed(
                $webhookEvent,
                sprintf('Failed to schedule sync for %s.', $sourceData->url),
                $reprocess,
            );
        }

        return $this->webhookCommandService->markSyncScheduled(
            $webhookEvent,
            $jobId,
            sprintf('Sync scheduled for %s.', $sourceData->url),
            $reprocess,
        );
    }

    private function webhookIdempotencyKey(WebhookEvent $webhookEvent): string
    {
        return sprintf(
            'webhook-sync:%s:%s:%s',
            $webhookEvent->sourceId(),
            $webhookEvent->provider(),
            $webhookEvent->getIdentifier()->toString(),
        );
    }

    private function mapEventToResult(WebhookEvent $webhookEvent): WebhookResultDto
    {
        $statusCode = match ($webhookEvent->status()) {
            'processed'            => 202,
            'ignored'              => 202,
            'sync_schedule_failed' => 500,
            // Signature failures never get here: they are answered with 401 before an event is stored.
            'rejected'             => WebhookInspectionResult::REPOSITORY_MISMATCH === $webhookEvent->message() ? 409 : 400,
            default                => 202,
        };

        return new WebhookResultDto($statusCode, $webhookEvent->message(), $webhookEvent->provider());
    }
}
