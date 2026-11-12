<?php

declare(strict_types=1);

namespace Packio\Publication\Api;

use Packio\Publication\Api\Dto\PublicationSyncResultDto;

interface PublicationApiFacadeInterface
{
    public function synchronizeSource(string $sourceId): PublicationSyncResultDto;
}
