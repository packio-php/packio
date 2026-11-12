<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Adapter;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Source\Infrastructure\Adapter\ProjectSettingsSourceProviderAvailabilityOverrideAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectSettingsSourceProviderAvailabilityOverrideAdapter::class)]
final class ProjectSettingsSourceProviderAvailabilityOverrideAdapterTest extends TestCase
{
    public function testProviderEnabledUsesProjectSettingsKey(): void
    {
        $projectSettings = $this->createMock(ProjectSettingsApiFacadeInterface::class);
        $projectSettings
            ->expects(self::once())
            ->method('booleanValue')
            ->with('source.providers.github.enabled', true)
            ->willReturn(false)
        ;

        $projectSettingsSourceProviderAvailabilityOverrideAdapter = new ProjectSettingsSourceProviderAvailabilityOverrideAdapter($projectSettings);

        self::assertFalse($projectSettingsSourceProviderAvailabilityOverrideAdapter->isProviderEnabled('github', true));
    }
}
