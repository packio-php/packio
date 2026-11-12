<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\Codeberg;

use Packio\Source\Domain\Dto\ProviderResourceData;

interface CodebergProviderResourceFetcherInterface
{
    /**
     * @return list<ProviderResourceData>
     */
    public function fetchResources(?string $accessToken = null): array;
}
