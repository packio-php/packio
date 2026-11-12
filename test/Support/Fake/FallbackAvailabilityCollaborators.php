<?php

declare(strict_types=1);

namespace PackioTest\Support\Fake;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Source\Application\Port\SourceProviderAvailabilityOverridePort;
use Packio\Source\Application\Provider\SourceProviderAvailabilityResolver;

/**
 * Runtime availability overrides that always answer with the configured fallback.
 */
trait FallbackAvailabilityCollaborators
{
    private function fallbackProjectSettings(): ProjectSettingsApiFacadeInterface
    {
        $projectSettingsApiFacade = $this->createStub(ProjectSettingsApiFacadeInterface::class);
        $projectSettingsApiFacade->method('booleanValue')->willReturnArgument(1);

        return $projectSettingsApiFacade;
    }

    /**
     * @param array<string, mixed> $sourceConfig
     */
    private function availabilityResolver(array $sourceConfig): SourceProviderAvailabilityResolver
    {
        $overridePort = $this->createStub(SourceProviderAvailabilityOverridePort::class);
        $overridePort->method('isProviderEnabled')->willReturnArgument(1);

        return new SourceProviderAvailabilityResolver($sourceConfig, $overridePort);
    }
}
