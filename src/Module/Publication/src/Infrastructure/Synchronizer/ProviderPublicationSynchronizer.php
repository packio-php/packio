<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Synchronizer;

use Common\Logging\SafeExceptionContext;
use Packio\Publication\Application\Dto\PublicationSyncResult;
use Packio\Publication\Application\Port\SynchronizerDependenciesPort;
use Packio\Publication\Application\Service\ComposerVersionResolver;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateProviderInterface;
use Packio\Publication\Infrastructure\Git\GitReference;
use RuntimeException;
use Throwable;

use function array_map;
use function count;
use function date;
use function is_array;
use function sprintf;

final readonly class ProviderPublicationSynchronizer
{
    public function __construct(
        private SynchronizerDependenciesPort $synchronizerDependenciesPort,
        private ComposerVersionResolver $composerVersionResolver,
        private ComposerReleaseDateProviderInterface $composerReleaseDateProvider,
    ) {}

    /**
     * @param array<string, mixed> $source
     */
    public function synchronize(
        array $source,
        ?string $accessToken,
        ProviderPublicationAdapterInterface $providerPublicationAdapter,
    ): PublicationSyncResult {
        $credentials = $providerPublicationAdapter->credentials(
            $source,
            $accessToken ?? $this->synchronizerDependenciesPort->resolveSourceAuthorizationSecret($source['id']),
        );

        try {
            $snapshot     = $providerPublicationAdapter->snapshot($source, $credentials);
            $createdAt    = date(DATE_ATOM);
            $releaseDates = $this->composerReleaseDateProvider->resolve(
                $source,
                $providerPublicationAdapter->accessToken($credentials),
                $createdAt,
            );
            $references = GitReference::uniqueByName([
                ...$snapshot->tags,
                ...$snapshot->branches,
                new GitReference($snapshot->defaultBranch, $snapshot->defaultBranch, GitReference::TYPE_BRANCH),
            ]);

            $package           = null;
            $releases          = [];
            $existingPackage   = null;
            $created           = false;
            $skippedReferences = [];
            $publishedVersions = [];

            foreach ($references as $reference) {
                try {
                    $metadata = $providerPublicationAdapter->fetchMetadata($source, $reference, $snapshot->defaultBranch, $credentials);
                    if (! is_array($package)) {
                        $created = ! is_array($existingPackage);
                        $package = $this->upsertPackageFromMetadata(
                            $providerPublicationAdapter,
                            $metadata,
                            $source['id'],
                            $createdAt,
                            $source['visibility'],
                            $source['organizationId'],
                            $existingPackage,
                        );
                        $existingPackage = $package;
                    }

                    $version = $this->composerVersionResolver->resolve(
                        $metadata->composer,
                        $metadata->refName,
                        $metadata->defaultBranch,
                    );
                    if (isset($publishedVersions[$version])) {
                        $skippedReferences[] = [
                            'reference' => $reference->name,
                            'error'     => sprintf(
                                'Resolved Composer version "%s" was already published from reference "%s".',
                                $version,
                                $publishedVersions[$version],
                            ),
                        ];

                        continue;
                    }

                    $release = $this->synchronizerDependenciesPort->publishRelease(
                        $providerPublicationAdapter->releasePayload(
                            $metadata,
                            $package,
                            $createdAt,
                            $source['id'],
                            $releaseDates,
                            $version,
                        ),
                    );
                    $publishedVersions[$version] = $reference->name;
                    $releases[]                  = $release;
                } catch (Throwable $exception) {
                    $skippedReferences[] = [
                        'reference' => $reference->name,
                        'error'     => SafeExceptionContext::redact($exception->getMessage()),
                    ];
                }
            }

            if (! is_array($package)) {
                if ([] !== $skippedReferences) {
                    throw new RuntimeException($skippedReferences[0]['error']);
                }

                throw new RuntimeException('No publishable versions were found in the repository.');
            }

            $readmeSync   = $providerPublicationAdapter->syncReadme($source, $package['name'], $snapshot->defaultBranch, $credentials);
            $syncedSource = $this->synchronizerDependenciesPort->markSyncSucceeded($source['id']);
            $source       = is_array($syncedSource) ? $syncedSource : $source;
            $this->synchronizerDependenciesPort->recordAudit(
                sprintf('publication.%s.completed', $providerPublicationAdapter->provider()),
                [
                    'repository'               => $source['url'],
                    'package'                  => $package['name'],
                    'versions'                 => array_map(
                        static fn (array $release): string => $release['version'],
                        $releases,
                    ),
                    'versions_count'           => count($releases),
                    'skipped_references'       => $skippedReferences,
                    'skipped_references_count' => count($skippedReferences),
                ],
                organizationId: $source['organizationId'],
                sourceId: $source['id'],
                packageName: $package['name'],
            );

            return new PublicationSyncResult(
                source: $source,
                package: $package,
                releases: $releases,
                created: $created,
                readmeSync: $readmeSync,
            );
        } catch (Throwable $exception) {
            $this->synchronizerDependenciesPort->markSyncFailed($source['id'], SafeExceptionContext::redact($exception->getMessage()));

            throw $exception;
        }
    }

    /**
     * @param null|array<string, mixed> $existingPackage
     *
     * @return array<string, mixed>
     */
    private function upsertPackageFromMetadata(
        ProviderPublicationAdapterInterface $providerPublicationAdapter,
        ProviderPackageMetadata $providerPackageMetadata,
        string $sourceId,
        string $createdAt,
        string $visibility,
        string $organizationId,
        ?array &$existingPackage,
    ): array {
        $packageName = (string) ($providerPackageMetadata->composer['name'] ?? '');
        if ('' === $packageName) {
            throw new RuntimeException('composer.json does not contain a valid package name.');
        }

        $existingPackage ??= $this->synchronizerDependenciesPort->findPackageInOrganization(
            $organizationId,
            $packageName,
        );
        if (is_array($existingPackage)) {
            $packageVisibility = $existingPackage['visibility'];
            $packageActive     = $existingPackage['active'] ?? true;
            $packageCreatedAt  = $existingPackage['createdAt'];
        } else {
            // shortcut: a source settings change between this read and the insert still wins the race; lock the source row if that window matters.
            $freshSource       = $this->synchronizerDependenciesPort->findSource($sourceId) ?? [];
            $packageVisibility = $freshSource['visibility'] ?? $visibility;
            $packageActive     = ! (bool) ($freshSource['archived'] ?? false);
            $packageCreatedAt  = $createdAt;
        }

        return $this->synchronizerDependenciesPort->upsertPackage([
            'name'           => $packageName,
            'type'           => (string) ($providerPackageMetadata->composer['type'] ?? 'library'),
            'description'    => (string) ($providerPackageMetadata->composer['description'] ?? ''),
            'visibility'     => $packageVisibility,
            'active'         => $packageActive,
            'sourceType'     => $providerPublicationAdapter->provider(),
            'sourceUrl'      => $providerPublicationAdapter->sourceUrl($providerPackageMetadata),
            'createdAt'      => $packageCreatedAt,
            'updatedAt'      => $createdAt,
            'organizationId' => $organizationId,
            'sourceId'       => $sourceId,
        ]);
    }
}
