<?php

declare(strict_types=1);

namespace Packio\Source\Application\Provider;

use function str_replace;
use function ucwords;

final readonly class SourceProviderLabelResolver
{
    public function __construct(private SourceProviderRegistryInterface $sourceProviderRegistry) {}

    public function resolve(string $provider): string
    {
        return $this->sourceProviderRegistry->label($provider) ?? ucwords(str_replace('_', ' ', $provider));
    }
}
