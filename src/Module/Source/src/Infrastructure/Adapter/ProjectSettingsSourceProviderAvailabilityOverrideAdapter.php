<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Source\Application\Port\SourceProviderAvailabilityOverridePort;

final readonly class ProjectSettingsSourceProviderAvailabilityOverrideAdapter implements SourceProviderAvailabilityOverridePort
{
    public function __construct(private ProjectSettingsApiFacadeInterface $projectSettingsApiFacade) {}

    public function isProviderEnabled(string $providerKey, bool $fallback): bool
    {
        return $this->projectSettingsApiFacade->booleanValue(
            'source.providers.' . $providerKey . '.enabled',
            $fallback,
        );
    }
}
