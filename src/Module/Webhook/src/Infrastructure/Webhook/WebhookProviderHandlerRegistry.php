<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Webhook;

final readonly class WebhookProviderHandlerRegistry
{
    /** @var array<string, WebhookProviderHandlerInterface> */
    private array $handlers;

    /**
     * @param list<WebhookProviderHandlerInterface> $handlers
     */
    public function __construct(array $handlers)
    {
        $indexed = [];
        foreach ($handlers as $handler) {
            $indexed[$handler->provider()] = $handler;
        }

        $this->handlers = $indexed;
    }

    public function handler(string $provider): ?WebhookProviderHandlerInterface
    {
        return $this->handlers[$provider] ?? null;
    }
}
