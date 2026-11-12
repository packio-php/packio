<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Webhook;

use Packio\Webhook\Application\Command\WebhookInspectionResult;
use Packio\Webhook\Application\Dto\SourceData;

interface WebhookProviderHandlerInterface
{
    public function provider(): string;

    /**
     * @param array<string, string> $headers
     */
    public function envelope(SourceData $sourceData, array $headers, string $payload): WebhookEnvelope;

    public function validateSignature(SourceData $sourceData, WebhookEnvelope $webhookEnvelope, string $payload): ?string;

    public function inspectPayload(SourceData $sourceData, WebhookEnvelope $webhookEnvelope, string $payload): WebhookInspectionResult;
}
