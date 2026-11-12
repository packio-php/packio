<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Synchronizer;

use LogicException;
use Packio\Publication\Application\Service\PackageReadmeSyncService;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Github\GithubAccessCredentials;
use Packio\Publication\Infrastructure\Github\GithubComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\Github\GithubPackageMetadata;
use Packio\Publication\Infrastructure\Readme\PackageReadmeExtractorInterface;

use function sprintf;

final readonly class GithubPublicationAdapter implements ProviderPublicationAdapterInterface
{
    public function __construct(
        private GithubComposerMetadataFetcherInterface $githubComposerMetadataFetcher,
        private PackageReadmeExtractorInterface $packageReadmeExtractor,
        private PackageReadmeSyncService $packageReadmeSyncService,
    ) {}

    public function provider(): string
    {
        return 'github';
    }

    public function credentials(array $source, ?string $accessToken): object
    {
        return new GithubAccessCredentials($accessToken);
    }

    public function accessToken(object $credentials): ?string
    {
        return $this->credentialsOf($credentials)->token;
    }

    public function snapshot(array $source, object $credentials): ProviderRepositorySnapshot
    {
        $snapshot = $this->githubComposerMetadataFetcher->snapshot(
            $source['owner'],
            $source['repository'],
            $this->credentialsOf($credentials),
        );

        return new ProviderRepositorySnapshot(
            defaultBranch: $snapshot->defaultBranch,
            tags: $snapshot->tags,
            branches: $snapshot->branches,
        );
    }

    public function fetchMetadata(
        array $source,
        GitReference $gitReference,
        string $defaultBranch,
        object $credentials
    ): ProviderPackageMetadata {
        $githubPackageMetadata = $this->githubComposerMetadataFetcher->fetchReference(
            $source['owner'],
            $source['repository'],
            $gitReference,
            $defaultBranch,
            $this->credentialsOf($credentials),
        );

        return $this->metadata($githubPackageMetadata);
    }

    public function sourceUrl(ProviderPackageMetadata $providerPackageMetadata): string
    {
        $githubPackageMetadata = $this->metadataOf($providerPackageMetadata);

        return sprintf(
            'https://github.com/%s/%s',
            $githubPackageMetadata->owner,
            $githubPackageMetadata->repository,
        );
    }

    public function releasePayload(
        ProviderPackageMetadata $providerPackageMetadata,
        array $package,
        string $createdAt,
        string $sourceId,
        ComposerReleaseDateMap $composerReleaseDateMap,
        string $version,
    ): array {
        $githubPackageMetadata = $this->metadataOf($providerPackageMetadata);

        return [
            'packageName'       => $package['name'],
            'version'           => $version,
            'reference'         => $githubPackageMetadata->commitReference,
            'upstreamReference' => $githubPackageMetadata->refName,
            'referenceType'     => $githubPackageMetadata->refType,
            'composer'          => $githubPackageMetadata->composer,
            'source'            => [
                'source_id' => $sourceId,
                'type'      => 'git',
                'url'       => sprintf(
                    'https://github.com/%s/%s.git',
                    $githubPackageMetadata->owner,
                    $githubPackageMetadata->repository,
                ),
                'reference' => $githubPackageMetadata->commitReference,
            ],
            'dist'              => [
                'type'      => 'zip',
                'url'       => sprintf(
                    'https://api.github.com/repos/%s/%s/zipball/%s',
                    $githubPackageMetadata->owner,
                    $githubPackageMetadata->repository,
                    $githubPackageMetadata->commitReference,
                ),
                'reference' => $githubPackageMetadata->commitReference,
            ],
            'createdAt'         => $createdAt,
            'releasedAt'        => $composerReleaseDateMap->find($version, $githubPackageMetadata->refName),
            'organizationId'    => $package['organizationId'],
        ];
    }

    public function syncReadme(
        array $source,
        string $packageName,
        string $defaultBranch,
        object $credentials,
    ): array {
        return $this->packageReadmeSyncService->synchronize(
            $source['organizationId'],
            $packageName,
            fn (): ?string => $this->packageReadmeExtractor->extractGithubReadmeHtml(
                $source['owner'],
                $source['repository'],
                $defaultBranch,
                $this->credentialsOf($credentials),
            ),
        );
    }

    private function credentialsOf(object $credentials): GithubAccessCredentials
    {
        if (! $credentials instanceof GithubAccessCredentials) {
            throw new LogicException('Expected GitHub access credentials.');
        }

        return $credentials;
    }

    private function metadata(GithubPackageMetadata $githubPackageMetadata): ProviderPackageMetadata
    {
        return new ProviderPackageMetadata(
            nativeMetadata: $githubPackageMetadata,
            composer: $githubPackageMetadata->composer,
            refName: $githubPackageMetadata->refName,
            defaultBranch: $githubPackageMetadata->defaultBranch,
            commitReference: $githubPackageMetadata->commitReference,
            refType: $githubPackageMetadata->refType,
        );
    }

    private function metadataOf(ProviderPackageMetadata $providerPackageMetadata): GithubPackageMetadata
    {
        if (! $providerPackageMetadata->nativeMetadata instanceof GithubPackageMetadata) {
            throw new LogicException('Expected GitHub package metadata.');
        }

        return $providerPackageMetadata->nativeMetadata;
    }
}
