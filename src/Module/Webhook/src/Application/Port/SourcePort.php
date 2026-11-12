<?php

declare(strict_types=1);

namespace Packio\Webhook\Application\Port;

use Packio\Webhook\Application\Dto\SourceData;

interface SourcePort
{
    public function findSource(string $sourceId): ?SourceData;

    public function scheduleSourceSyncOnce(SourceData $sourceData, string $idempotencyKey): string;
}
