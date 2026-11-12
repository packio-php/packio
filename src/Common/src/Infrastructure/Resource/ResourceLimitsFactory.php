<?php

declare(strict_types=1);

namespace Common\Infrastructure\Resource;

use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;

final class ResourceLimitsFactory
{
    public function __invoke(ContainerInterface $container): ResourceLimits
    {
        $configReader = ConfigReader::fromArray($container->get('config'), self::class);

        return new ResourceLimits(
            webhookBytes: $configReader->int('resource_limits.webhook_bytes', 1_048_576),
            requestBytes: $configReader->int('resource_limits.request_bytes', 33_554_432),
            upstreamBytes: $configReader->int('resource_limits.upstream_bytes', 16_777_216),
            gitSeconds: $configReader->int('resource_limits.git_seconds', 120),
            gitWorkspaceBytes: $configReader->int('resource_limits.git_workspace_bytes', 1_073_741_824),
            archiveBytes: $configReader->int('storage.archive.max_archive_bytes', 52_428_800),
            gitMemoryBytes: $configReader->int('resource_limits.git_memory_bytes', 536_870_912),
        );
    }
}
