<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Webhook;

use Packio\Source\Application\Service\WebhookRegistrarInterface;
use Packio\Source\Application\Service\WebhookRegistrarRegistryInterface;

use function array_key_exists;

final class SourceWebhookRegistrarRegistry implements WebhookRegistrarRegistryInterface
{
    /** @var array<string, WebhookRegistrarInterface> */
    private array $registrars = [];

    /**
     * @param iterable<WebhookRegistrarInterface> $registrars
     */
    public function __construct(iterable $registrars = [])
    {
        foreach ($registrars as $registrar) {
            $this->registrars[$registrar->provider()] = $registrar;
        }
    }

    public function hasProvider(string $provider): bool
    {
        return array_key_exists($provider, $this->registrars);
    }

    public function forProvider(string $provider): ?WebhookRegistrarInterface
    {
        return $this->registrars[$provider] ?? null;
    }
}
