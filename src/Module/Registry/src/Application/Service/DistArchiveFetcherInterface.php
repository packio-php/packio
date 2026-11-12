<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Service;

use Packio\Registry\Application\Storage\DistArchiveStream;

interface DistArchiveFetcherInterface
{
    /**
     * @param array<string, mixed> $providerConfig
     */
    public function fetchArchive(
        string $provider,
        string $owner,
        string $repository,
        string $reference,
        ?string $token = null,
        string $baseUrl = '',
        array $providerConfig = [],
    ): DistArchiveStream;
}
