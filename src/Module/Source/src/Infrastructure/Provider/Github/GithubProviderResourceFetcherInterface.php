<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\Github;

use Packio\Source\Domain\Dto\ProviderResourceData;

interface GithubProviderResourceFetcherInterface
{
    /**
     * @return list<ProviderResourceData>
     */
    public function fetchResources(?string $token = null): array;
}
