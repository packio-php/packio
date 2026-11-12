<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\GitlabSelfHosted;

use Packio\Source\Domain\Dto\ProviderResourceData;

interface GitlabSelfHostedProviderResourceFetcherInterface
{
    /**
     * @return list<ProviderResourceData>
     */
    public function fetchResources(string $baseUrl, ?string $token = null): array;
}
