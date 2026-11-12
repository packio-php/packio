<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Infrastructure\Resource;

use Common\Infrastructure\Resource\ResourceLimits;
use Common\Infrastructure\Resource\ResourceLimitsFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ResourceLimitsTest extends TestCase
{
    public function testInstallationConfigurationOverridesLimits(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with('config')->willReturn([
            'resource_limits' => [
                'webhook_bytes'       => 8,
                'request_bytes'       => 16,
                'upstream_bytes'      => 32,
                'git_memory_bytes'    => 128,
                'git_seconds'         => 7,
                'git_workspace_bytes' => 64,
            ],
            'storage'         => [
                'archive' => [
                    'max_archive_bytes' => 48,
                ],
            ],
        ]);
        $limits = new ResourceLimitsFactory()($container);
        self::assertSame(8, $limits->webhookBytes);
        self::assertSame(16, $limits->requestBytes);
        self::assertSame(32, $limits->upstreamBytes);
        self::assertSame(7.0, $limits->gitSeconds);
        self::assertSame(64, $limits->gitWorkspaceBytes);
        self::assertSame(128, $limits->gitMemoryBytes);
        self::assertSame(48, $limits->archiveBytes);
    }

    public function testZeroCannotDisableLimits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ResourceLimits(upstreamBytes: 0);
    }
}
