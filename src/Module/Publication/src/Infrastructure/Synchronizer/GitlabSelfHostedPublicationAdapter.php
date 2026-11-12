<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Synchronizer;

use LogicException;
use Packio\Publication\Application\Service\PackageReadmeSyncService;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\GitlabSelfHosted\GitlabSelfHostedAccessCredentials;
use Packio\Publication\Infrastructure\GitlabSelfHosted\GitlabSelfHostedComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\GitlabSelfHosted\GitlabSelfHostedPackageMetadata;
use Packio\Publication\Infrastructure\Readme\GitlabSelfHostedPackageReadmeExtractorInterface;

use function sprintf;

final readonly class GitlabSelfHostedPublicationAdapter implements ProviderPublicationAdapterInterface
{
    public function __construct(
        private GitlabSelfHostedComposerMetadataFetcherInterface $gitlabSelfHostedComposerMetadataFetcher,
        private GitlabSelfHostedPackageReadmeExtractorInterface $gitlabSelfHostedPackageReadmeExtractor,
        private PackageReadmeSyncService $packageReadmeSyncService,
    ) {}

    public function provider(): string
    {
        return 'gitlab_self_hosted';
    }

    public function credentials(array $source, ?string $accessToken): object
    {
        return new GitlabSelfHostedAccessCredentials(
            baseUrl: (string) ($source['providerConfig']['base_url'] ?? ''),
            token: $accessToken,
        );
    }

    public function accessToken(object $credentials): ?string
    {
        return $this->credentialsOf($credentials)->token;
    }

    public function snapshot(array $source, object $credentials): ProviderRepositorySnapshot
    {
        $snapshot = $this->gitlabSelfHostedComposerMetadataFetcher->snapshot(
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
        $gitlabSelfHostedPackageMetadata = $this->gitlabSelfHostedComposerMetadataFetcher->fetchReference(
            $source['owner'],
            $source['repository'],
            $gitReference,
            $defaultBranch,
            $this->credentialsOf($credentials),
        );

        return $this->metadata($gitlabSelfHostedPackageMetadata);
    }

    public function sourceUrl(ProviderPackageMetadata $providerPackageMetadata): string
    {
        $gitlabSelfHostedPackageMetadata = $this->metadataOf($providerPackageMetadata);

        return sprintf(
            '%s/%s/%s',
            $gitlabSelfHostedPackageMetadata->baseUrl,
            $gitlabSelfHostedPackageMetadata->namespace,
            $gitlabSelfHostedPackageMetadata->repository,
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
        $gitlabSelfHostedPackageMetadata = $this->metadataOf($providerPackageMetadata);

        return [
            'packageName'       => $package['name'],
            'version'           => $version,
            'reference'         => $gitlabSelfHostedPackageMetadata->commitReference,
            'upstreamReference' => $gitlabSelfHostedPackageMetadata->refName,
            'referenceType'     => $gitlabSelfHostedPackageMetadata->refType,
            'composer'          => $gitlabSelfHostedPackageMetadata->composer,
            'source'            => [
                'source_id' => $sourceId,
                'type'      => 'git',
                'url'       => sprintf(
                    '%s/%s/%s.git',
                    $gitlabSelfHostedPackageMetadata->baseUrl,
                    $gitlabSelfHostedPackageMetadata->namespace,
                    $gitlabSelfHostedPackageMetadata->repository,
                ),
                'reference' => $gitlabSelfHostedPackageMetadata->commitReference,
            ],
            'dist'              => [
                'type'      => 'zip',
                'url'       => sprintf(
                    '%s/%s/%s/-/archive/%s/%s-%s.zip',
                    $gitlabSelfHostedPackageMetadata->baseUrl,
                    $gitlabSelfHostedPackageMetadata->namespace,
                    $gitlabSelfHostedPackageMetadata->repository,
                    $gitlabSelfHostedPackageMetadata->commitReference,
                    $gitlabSelfHostedPackageMetadata->repository,
                    $gitlabSelfHostedPackageMetadata->commitReference,
                ),
                'reference' => $gitlabSelfHostedPackageMetadata->commitReference,
            ],
            'createdAt'         => $createdAt,
            'releasedAt'        => $composerReleaseDateMap->find($version, $gitlabSelfHostedPackageMetadata->refName),
            'organizationId'    => $package['organizationId'],
        ];
    }

    public function syncReadme(array $source, string $packageName, string $defaultBranch, object $credentials): array
    {
        return $this->packageReadmeSyncService->synchronize(
            $source['organizationId'],
            $packageName,
            fn (): ?string => $this->gitlabSelfHostedPackageReadmeExtractor->extractGitlabSelfHostedReadmeHtml(
                $source['owner'],
                $source['repository'],
                $defaultBranch,
                $this->credentialsOf($credentials),
            ),
        );
    }

    private function credentialsOf(object $credentials): GitlabSelfHostedAccessCredentials
    {
        if (! $credentials instanceof GitlabSelfHostedAccessCredentials) {
            throw new LogicException('Expected self-hosted GitLab access credentials.');
        }

        return $credentials;
    }

    private function metadata(GitlabSelfHostedPackageMetadata $gitlabSelfHostedPackageMetadata): ProviderPackageMetadata
    {
        return new ProviderPackageMetadata(
            $gitlabSelfHostedPackageMetadata,
            $gitlabSelfHostedPackageMetadata->composer,
            $gitlabSelfHostedPackageMetadata->refName,
            $gitlabSelfHostedPackageMetadata->defaultBranch,
            $gitlabSelfHostedPackageMetadata->commitReference,
            $gitlabSelfHostedPackageMetadata->refType,
        );
    }

    private function metadataOf(ProviderPackageMetadata $providerPackageMetadata): GitlabSelfHostedPackageMetadata
    {
        if (! $providerPackageMetadata->nativeMetadata instanceof GitlabSelfHostedPackageMetadata) {
            throw new LogicException('Expected self-hosted GitLab package metadata.');
        }

        return $providerPackageMetadata->nativeMetadata;
    }
}
