<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Synchronizer;

use LogicException;
use Packio\Publication\Application\Service\PackageReadmeSyncService;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Gitlab\GitlabAccessCredentials;
use Packio\Publication\Infrastructure\Gitlab\GitlabComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\Gitlab\GitlabPackageMetadata;
use Packio\Publication\Infrastructure\Readme\GitlabPackageReadmeExtractorInterface;

use function sprintf;

final readonly class GitlabPublicationAdapter implements ProviderPublicationAdapterInterface
{
    public function __construct(
        private GitlabComposerMetadataFetcherInterface $gitlabComposerMetadataFetcher,
        private GitlabPackageReadmeExtractorInterface $gitlabPackageReadmeExtractor,
        private PackageReadmeSyncService $packageReadmeSyncService,
    ) {}

    public function provider(): string
    {
        return 'gitlab';
    }

    public function credentials(array $source, ?string $accessToken): object
    {
        return new GitlabAccessCredentials($accessToken);
    }

    public function accessToken(object $credentials): ?string
    {
        return $this->credentialsOf($credentials)->token;
    }

    public function snapshot(array $source, object $credentials): ProviderRepositorySnapshot
    {
        $snapshot = $this->gitlabComposerMetadataFetcher->snapshot(
            $source['owner'],
            $source['repository'],
            $this->credentialsOf($credentials),
        );

        return new ProviderRepositorySnapshot($snapshot->defaultBranch, $snapshot->tags, $snapshot->branches);
    }

    public function fetchMetadata(
        array $source,
        GitReference $gitReference,
        string $defaultBranch,
        object $credentials
    ): ProviderPackageMetadata {
        $gitlabPackageMetadata = $this->gitlabComposerMetadataFetcher->fetchReference(
            $source['owner'],
            $source['repository'],
            $gitReference,
            $defaultBranch,
            $this->credentialsOf($credentials),
        );

        return $this->metadata($gitlabPackageMetadata);
    }

    public function sourceUrl(ProviderPackageMetadata $providerPackageMetadata): string
    {
        $gitlabPackageMetadata = $this->metadataOf($providerPackageMetadata);

        return sprintf(
            'https://gitlab.com/%s/%s',
            $gitlabPackageMetadata->namespace,
            $gitlabPackageMetadata->repository,
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
        $gitlabPackageMetadata = $this->metadataOf($providerPackageMetadata);

        return [
            'packageName'       => $package['name'],
            'version'           => $version,
            'reference'         => $gitlabPackageMetadata->commitReference,
            'upstreamReference' => $gitlabPackageMetadata->refName,
            'referenceType'     => $gitlabPackageMetadata->refType,
            'composer'          => $gitlabPackageMetadata->composer,
            'source'            => [
                'source_id' => $sourceId,
                'type'      => 'git',
                'url'       => sprintf(
                    'https://gitlab.com/%s/%s.git',
                    $gitlabPackageMetadata->namespace,
                    $gitlabPackageMetadata->repository,
                ),
                'reference' => $gitlabPackageMetadata->commitReference,
            ],
            'dist'              => [
                'type'      => 'zip',
                'url'       => sprintf(
                    'https://gitlab.com/%s/%s/-/archive/%s/%s-%s.zip',
                    $gitlabPackageMetadata->namespace,
                    $gitlabPackageMetadata->repository,
                    $gitlabPackageMetadata->commitReference,
                    $gitlabPackageMetadata->repository,
                    $gitlabPackageMetadata->commitReference,
                ),
                'reference' => $gitlabPackageMetadata->commitReference,
            ],
            'createdAt'         => $createdAt,
            'releasedAt'        => $composerReleaseDateMap->find($version, $gitlabPackageMetadata->refName),
            'organizationId'    => $package['organizationId'],
        ];
    }

    public function syncReadme(array $source, string $packageName, string $defaultBranch, object $credentials): array
    {
        return $this->packageReadmeSyncService->synchronize(
            $source['organizationId'],
            $packageName,
            fn (): ?string => $this->gitlabPackageReadmeExtractor->extractGitlabReadmeHtml(
                $source['owner'],
                $source['repository'],
                $defaultBranch,
                $this->credentialsOf($credentials),
            ),
        );
    }

    private function credentialsOf(object $credentials): GitlabAccessCredentials
    {
        if (! $credentials instanceof GitlabAccessCredentials) {
            throw new LogicException('Expected GitLab access credentials.');
        }

        return $credentials;
    }

    private function metadata(GitlabPackageMetadata $gitlabPackageMetadata): ProviderPackageMetadata
    {
        return new ProviderPackageMetadata(
            $gitlabPackageMetadata,
            $gitlabPackageMetadata->composer,
            $gitlabPackageMetadata->refName,
            $gitlabPackageMetadata->defaultBranch,
            $gitlabPackageMetadata->commitReference,
            $gitlabPackageMetadata->refType,
        );
    }

    private function metadataOf(ProviderPackageMetadata $providerPackageMetadata): GitlabPackageMetadata
    {
        if (! $providerPackageMetadata->nativeMetadata instanceof GitlabPackageMetadata) {
            throw new LogicException('Expected GitLab package metadata.');
        }

        return $providerPackageMetadata->nativeMetadata;
    }
}
