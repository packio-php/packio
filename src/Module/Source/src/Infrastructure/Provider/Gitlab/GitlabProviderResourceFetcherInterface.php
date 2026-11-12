<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\Gitlab;

use Packio\Source\Domain\Dto\ProviderResourceData;

interface GitlabProviderResourceFetcherInterface
{
    /**
     * @return list<ProviderResourceData>
     */
    public function fetchResources(?string $token = null): array;
}
