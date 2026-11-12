<?php

declare(strict_types=1);

namespace Packio\Source\Application\Provider;

use InvalidArgumentException;
use Packio\Source\Domain\Provider\SourceProviderInterface;

use function sprintf;

final readonly class SourceProviderRegistry implements SourceProviderRegistryInterface
{
    /**
     * @param iterable<SourceProviderInterface> $providers
     */
    public function __construct(
        private iterable $providers,
        private SourceProviderAvailabilityResolver $sourceProviderAvailabilityResolver
    ) {}

    public function provider(string $key): SourceProviderInterface
    {
        foreach ($this->providers as $provider) {
            $descriptor = $provider->descriptor();

            if (
                $descriptor->name === $key
                && $this->sourceProviderAvailabilityResolver->isEnabled($descriptor->name)
            ) {
                return $provider;
            }
        }

        throw new InvalidArgumentException(
            sprintf('Source provider "%s" is not registered.', $key),
        );
    }

    public function label(string $key): ?string
    {
        foreach ($this->providers as $provider) {
            $descriptor = $provider->descriptor();

            if ($descriptor->name === $key) {
                return $descriptor->label;
            }
        }

        return null;
    }

    public function providers(): array
    {
        $providers = [];

        foreach ($this->providers as $provider) {
            if (! $this->sourceProviderAvailabilityResolver->isEnabled($provider->descriptor()->name)) {
                continue;
            }

            $providers[] = $provider;
        }

        return $providers;
    }
}
