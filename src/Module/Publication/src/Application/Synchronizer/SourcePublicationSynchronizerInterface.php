<?php

declare(strict_types=1);

namespace Packio\Publication\Application\Synchronizer;

use Packio\Publication\Application\Dto\PublicationSyncResult;

interface SourcePublicationSynchronizerInterface
{
    public function provider(): string;

    /**
     * @param array<string, mixed> $source Source data (camelCase keys)
     */
    public function synchronize(array $source, ?string $accessToken = null): PublicationSyncResult;
}
