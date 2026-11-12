<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Webhook;

use function in_array;

final readonly class WebhookProviderHandlerConfig
{
    /**
     * @param list<string> $events
     */
    public function __construct(
        public string $provider,
        public array $events,
        public ?string $signatureHeader = null,
    ) {}

    public function supportsEvent(string $event): bool
    {
        return in_array($event, $this->events, true);
    }

    public function signatureHeaderOr(string $default): string
    {
        return $this->signatureHeader ?? $default;
    }
}
