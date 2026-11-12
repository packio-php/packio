<?php

declare(strict_types=1);

namespace Packio\Source\Application\Provider;

use Packio\Source\Domain\Provider\SourceProviderInterface;

interface SourceProviderRegistryInterface
{
    public function provider(string $key): SourceProviderInterface;

    /** Descriptor label regardless of availability; null for an unknown key. */
    public function label(string $key): ?string;

    /**
     * @return list<SourceProviderInterface>
     */
    public function providers(): array;
}
