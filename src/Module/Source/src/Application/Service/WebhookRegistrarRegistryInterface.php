<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

interface WebhookRegistrarRegistryInterface
{
    public function forProvider(string $provider): ?WebhookRegistrarInterface;

    public function hasProvider(string $provider): bool;
}
