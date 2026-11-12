<?php

declare(strict_types=1);

namespace Packio\Source\Application\Port;

use Packio\Source\Application\Dto\SourceSyncResult;

interface PublicationSyncPort
{
    public function synchronizeSource(string $sourceId): SourceSyncResult;
}
