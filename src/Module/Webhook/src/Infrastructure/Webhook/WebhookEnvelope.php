<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Webhook;

final readonly class WebhookEnvelope
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $provider,
        public string $deliveryId,
        public string $event,
        public array $headers = [],
    ) {}
}
