<?php

declare(strict_types=1);

namespace Common\Infrastructure\Resource;

use InvalidArgumentException;

final readonly class ResourceLimits
{
    public function __construct(
        public int $webhookBytes = 1_048_576,
        public int $requestBytes = 33_554_432,
        public int $upstreamBytes = 16_777_216,
        public float $gitSeconds = 120.0,
        public int $gitWorkspaceBytes = 1_073_741_824,
        public int $archiveBytes = 52_428_800,
        public int $gitMemoryBytes = 536_870_912,
    ) {
        if ($webhookBytes < 1 || $requestBytes < 1 || $upstreamBytes < 1 || $gitSeconds <= 0 || $gitWorkspaceBytes < 1 || $archiveBytes < 1 || $gitMemoryBytes < 1) {
            throw new InvalidArgumentException('Resource limits must be positive.');
        }
    }
}
