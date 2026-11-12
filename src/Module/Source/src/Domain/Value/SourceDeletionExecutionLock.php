<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Value;

final class SourceDeletionExecutionLock
{
    public static function key(string $sourceId, string $organizationId): string
    {
        return 'source-deletion-execution:' . $organizationId . ':' . $sourceId;
    }
}
