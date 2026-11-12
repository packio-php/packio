<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter;

use Packio\Publication\Api\PublicationApiFacadeInterface;
use Packio\Source\Application\Dto\SourceSyncResult;
use Packio\Source\Application\Port\PublicationSyncPort;

final readonly class PublicationSyncAdapter implements PublicationSyncPort
{
    public function __construct(private PublicationApiFacadeInterface $publicationApiFacade) {}

    public function synchronizeSource(string $sourceId): SourceSyncResult
    {
        $publicationSyncResultDto = $this->publicationApiFacade->synchronizeSource($sourceId);

        return new SourceSyncResult(
            packageName: $publicationSyncResultDto->packageName,
            releases: $publicationSyncResultDto->releases,
            created: $publicationSyncResultDto->created,
            readmeSync: $publicationSyncResultDto->readmeSync,
            retention: $publicationSyncResultDto->retention,
        );
    }
}
