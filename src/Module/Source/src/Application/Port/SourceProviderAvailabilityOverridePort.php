<?php

declare(strict_types=1);

namespace Packio\Source\Application\Port;

interface SourceProviderAvailabilityOverridePort
{
    public function isProviderEnabled(string $providerKey, bool $fallback): bool;
}
