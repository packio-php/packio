<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\Bitbucket;

use Packio\Source\Domain\Dto\ProviderResourceData;

interface BitbucketProviderResourceFetcherInterface
{
    /**
     * @return list<ProviderResourceData>
     */
    public function fetchResources(?string $accessToken = null): array;
}
