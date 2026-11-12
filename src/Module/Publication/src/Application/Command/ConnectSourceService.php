<?php

declare(strict_types=1);

namespace Packio\Publication\Application\Command;

use Packio\Publication\Application\Dto\PublicationSyncResult;
use Packio\Publication\Application\Port\SynchronizerDependenciesPort;
use Packio\Publication\Application\Synchronizer\SourcePublicationSynchronizerRegistry;
use RuntimeException;

use function array_filter;
use function array_flip;
use function array_values;
use function in_array;
use function is_array;
use function trim;

final readonly class ConnectSourceService
{
    public function __construct(
        private SourcePublicationSynchronizerRegistry $sourcePublicationSynchronizerRegistry,
        private SynchronizerDependenciesPort $synchronizerDependenciesPort,
    ) {}

    public function synchronizeSource(string $sourceId): PublicationSyncResult
    {
        $source = $this->synchronizerDependenciesPort->findSource($sourceId);
        if (! is_array($source)) {
            throw new RuntimeException('Source not found.');
        }

        return $this->applyRetention(
            $this->sourcePublicationSynchronizerRegistry
                ->forProvider($source['provider'])
                ->synchronize($source)
        );
    }

    private function applyRetention(PublicationSyncResult $publicationSyncResult): PublicationSyncResult
    {
        $sourceId       = (string) ($publicationSyncResult->source['id'] ?? '');
        $organizationId = (string) ($publicationSyncResult->source['organizationId'] ?? '');
        $packageName    = (string) ($publicationSyncResult->package['name'] ?? '');

        if (in_array('', [trim($sourceId), trim($organizationId), trim($packageName)], true)) {
            return $publicationSyncResult;
        }

        $keepLastReleases = (int) ($publicationSyncResult->source['keepLastReleases'] ?? 0);
        $retention        = $this->synchronizerDependenciesPort->applyReleaseRetention(
            $organizationId,
            $sourceId,
            $packageName,
            $keepLastReleases,
        );

        $releases = $publicationSyncResult->releases;
        if ($keepLastReleases > 0) {
            $activeVersions = array_flip([
                ...(array) ($retention['kept_versions'] ?? []),
                ...(array) ($retention['ignored_dev_versions'] ?? []),
            ]);
            $releases = array_values(array_filter(
                $releases,
                static fn (array $release): bool => (bool) ($release['active'] ?? true)
                    && isset($activeVersions[(string) ($release['version'] ?? '')]),
            ));
        }

        return new PublicationSyncResult(
            source: $publicationSyncResult->source,
            package: $publicationSyncResult->package,
            releases: $releases,
            created: $publicationSyncResult->created,
            readmeSync: $publicationSyncResult->readmeSync,
            retention: $retention,
        );
    }
}
