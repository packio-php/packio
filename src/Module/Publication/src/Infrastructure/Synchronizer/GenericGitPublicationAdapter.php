<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Synchronizer;

use LogicException;
use Packio\Publication\Application\Service\PackageReadmeSyncService;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use Packio\Publication\Infrastructure\GenericGit\GenericGitAccessCredentials;
use Packio\Publication\Infrastructure\GenericGit\GenericGitComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\GenericGit\GenericGitPackageMetadata;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Readme\GenericGitPackageReadmeExtractorInterface;

use function is_string;
use function trim;

final readonly class GenericGitPublicationAdapter implements ProviderPublicationAdapterInterface
{
    public function __construct(
        private GenericGitComposerMetadataFetcherInterface $genericGitComposerMetadataFetcher,
        private GenericGitPackageReadmeExtractorInterface $genericGitPackageReadmeExtractor,
        private PackageReadmeSyncService $packageReadmeSyncService,
    ) {}

    public function provider(): string
    {
        return 'generic_git';
    }

    public function credentials(array $source, ?string $accessToken): object
    {
        $composerJsonPath = $source['providerConfig']['composer_json_path'] ?? 'composer.json';

        return new GenericGitAccessCredentials(
            cloneUrl: (string) ($source['providerConfig']['clone_url'] ?? ''),
            token: $accessToken,
            authScheme: (string) ($source['providerConfig']['auth_scheme'] ?? 'bearer'),
            composerJsonPath: is_string($composerJsonPath) && '' !== trim($composerJsonPath)
                ? trim($composerJsonPath)
                : 'composer.json',
        );
    }

    public function accessToken(object $credentials): ?string
    {
        return $this->credentialsOf($credentials)->token;
    }

    public function snapshot(array $source, object $credentials): ProviderRepositorySnapshot
    {
        $genericGitAccessCredentials = $this->credentialsOf($credentials);
        $snapshot                    = $this->genericGitComposerMetadataFetcher->snapshot(
            $genericGitAccessCredentials->cloneUrl,
            $genericGitAccessCredentials,
        );

        return new ProviderRepositorySnapshot($snapshot->defaultBranch, $snapshot->tags, $snapshot->branches);
    }

    public function fetchMetadata(
        array $source,
        GitReference $gitReference,
        string $defaultBranch,
        object $credentials
    ): ProviderPackageMetadata {
        $genericGitAccessCredentials = $this->credentialsOf($credentials);
        $genericGitPackageMetadata   = $this->genericGitComposerMetadataFetcher->fetchReference(
            $genericGitAccessCredentials->cloneUrl,
            $gitReference,
            $defaultBranch,
            $genericGitAccessCredentials,
        );

        return $this->metadata($genericGitPackageMetadata);
    }

    public function sourceUrl(ProviderPackageMetadata $providerPackageMetadata): string
    {
        return $this->metadataOf($providerPackageMetadata)->cloneUrl;
    }

    public function releasePayload(
        ProviderPackageMetadata $providerPackageMetadata,
        array $package,
        string $createdAt,
        string $sourceId,
        ComposerReleaseDateMap $composerReleaseDateMap,
        string $version,
    ): array {
        $genericGitPackageMetadata = $this->metadataOf($providerPackageMetadata);

        return [
            'packageName'       => $package['name'],
            'version'           => $version,
            'reference'         => $genericGitPackageMetadata->commitReference,
            'upstreamReference' => $genericGitPackageMetadata->refName,
            'referenceType'     => $genericGitPackageMetadata->refType,
            'composer'          => $genericGitPackageMetadata->composer,
            'source'            => [
                'source_id' => $sourceId,
                'type'      => 'git',
                'url'       => $genericGitPackageMetadata->cloneUrl,
                'reference' => $genericGitPackageMetadata->commitReference,
            ],
            'dist'              => [
                'type'      => 'zip',
                'url'       => '',
                'reference' => $genericGitPackageMetadata->commitReference,
            ],
            'createdAt'         => $createdAt,
            'releasedAt'        => $composerReleaseDateMap->find($version, $genericGitPackageMetadata->refName),
            'organizationId'    => $package['organizationId'],
        ];
    }

    public function syncReadme(array $source, string $packageName, string $defaultBranch, object $credentials): array
    {
        $genericGitAccessCredentials = $this->credentialsOf($credentials);

        return $this->packageReadmeSyncService->synchronize(
            $source['organizationId'],
            $packageName,
            fn (): ?string => $this->genericGitPackageReadmeExtractor->extractGenericGitReadmeHtml(
                $genericGitAccessCredentials->cloneUrl,
                $defaultBranch,
                $genericGitAccessCredentials,
            ),
        );
    }

    private function credentialsOf(object $credentials): GenericGitAccessCredentials
    {
        if (! $credentials instanceof GenericGitAccessCredentials) {
            throw new LogicException('Expected generic Git access credentials.');
        }

        return $credentials;
    }

    private function metadata(GenericGitPackageMetadata $genericGitPackageMetadata): ProviderPackageMetadata
    {
        return new ProviderPackageMetadata(
            $genericGitPackageMetadata,
            $genericGitPackageMetadata->composer,
            $genericGitPackageMetadata->refName,
            $genericGitPackageMetadata->defaultBranch,
            $genericGitPackageMetadata->commitReference,
            $genericGitPackageMetadata->refType,
        );
    }

    private function metadataOf(ProviderPackageMetadata $providerPackageMetadata): GenericGitPackageMetadata
    {
        if (! $providerPackageMetadata->nativeMetadata instanceof GenericGitPackageMetadata) {
            throw new LogicException('Expected generic Git package metadata.');
        }

        return $providerPackageMetadata->nativeMetadata;
    }
}
