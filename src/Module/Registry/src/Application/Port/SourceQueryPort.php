<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Port;

use Packio\Registry\Application\Dto\SourceData;

interface SourceQueryPort
{
    public function findSource(string $sourceId): ?SourceData;

    public function resolveSourceAuthorizationSecret(string $sourceId): ?string;
}
