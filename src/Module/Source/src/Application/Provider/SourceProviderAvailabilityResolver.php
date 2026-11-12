<?php

declare(strict_types=1);

namespace Packio\Source\Application\Provider;

use Packio\Source\Application\Port\SourceProviderAvailabilityOverridePort;
use Packio\Source\Domain\ValueObject\SourceProvider;

final readonly class SourceProviderAvailabilityResolver
{
    /**
     * @param array<string, mixed> $sourceConfig
     */
    public function __construct(
        private array $sourceConfig,
        private SourceProviderAvailabilityOverridePort $sourceProviderAvailabilityOverridePort,
    ) {}

    public function isEnabled(SourceProvider|string $provider): bool
    {
        $providerKey = $provider instanceof SourceProvider
            ? $provider->value
            : $provider;

        $fallback = (bool) ($this->sourceConfig['providers'][$providerKey]['enabled'] ?? true);

        return $this->sourceProviderAvailabilityOverridePort->isProviderEnabled(
            $providerKey,
            $fallback,
        );
    }
}
