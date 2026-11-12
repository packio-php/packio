<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Synchronizer;

use LogicException;
use Packio\Publication\Application\Service\PackageReadmeSyncService;
use Packio\Publication\Infrastructure\Bitbucket\BitbucketAccessCredentials;
use Packio\Publication\Infrastructure\Bitbucket\BitbucketComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\Bitbucket\BitbucketPackageMetadata;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Readme\BitbucketPackageReadmeExtractorInterface;

use function sprintf;

final readonly class BitbucketPublicationAdapter implements ProviderPublicationAdapterInterface
{
    public function __construct(
        private BitbucketComposerMetadataFetcherInterface $bitbucketComposerMetadataFetcher,
        private BitbucketPackageReadmeExtractorInterface $bitbucketPackageReadmeExtractor,
        private PackageReadmeSyncService $packageReadmeSyncService,
    ) {}

    public function provider(): string
    {
        return 'bitbucket';
    }

    public function credentials(array $source, ?string $accessToken): object
    {
        return new BitbucketAccessCredentials($accessToken);
    }

    public function accessToken(object $credentials): ?string
    {
        return $this->credentialsOf($credentials)->token;
    }

    public function snapshot(array $source, object $credentials): ProviderRepositorySnapshot
    {
        $snapshot = $this->bitbucketComposerMetadataFetcher->snapshot(
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
        $bitbucketPackageMetadata = $this->bitbucketComposerMetadataFetcher->fetchReference(
            $source['owner'],
            $source['repository'],
            $gitReference,
            $defaultBranch,
            $this->credentialsOf($credentials),
        );

        return $this->metadata($bitbucketPackageMetadata);
    }

    public function sourceUrl(ProviderPackageMetadata $providerPackageMetadata): string
    {
        $bitbucketPackageMetadata = $this->metadataOf($providerPackageMetadata);

        return sprintf(
            'https://bitbucket.org/%s/%s',
            $bitbucketPackageMetadata->workspace,
            $bitbucketPackageMetadata->repository,
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
        $bitbucketPackageMetadata = $this->metadataOf($providerPackageMetadata);

        return [
            'packageName'       => $package['name'],
            'version'           => $version,
            'reference'         => $bitbucketPackageMetadata->commitReference,
            'upstreamReference' => $bitbucketPackageMetadata->refName,
            'referenceType'     => $bitbucketPackageMetadata->refType,
            'composer'          => $bitbucketPackageMetadata->composer,
            'source'            => [
                'source_id' => $sourceId,
                'type'      => 'git',
                'url'       => sprintf(
                    'https://bitbucket.org/%s/%s.git',
                    $bitbucketPackageMetadata->workspace,
                    $bitbucketPackageMetadata->repository,
                ),
                'reference' => $bitbucketPackageMetadata->commitReference,
            ],
            'dist'              => [
                'type'      => 'zip',
                'url'       => sprintf(
                    'https://bitbucket.org/%s/%s/get/%s.zip',
                    $bitbucketPackageMetadata->workspace,
                    $bitbucketPackageMetadata->repository,
                    $bitbucketPackageMetadata->commitReference,
                ),
                'reference' => $bitbucketPackageMetadata->commitReference,
            ],
            'createdAt'         => $createdAt,
            'releasedAt'        => $composerReleaseDateMap->find($version, $bitbucketPackageMetadata->refName),
            'organizationId'    => $package['organizationId'],
        ];
    }

    public function syncReadme(array $source, string $packageName, string $defaultBranch, object $credentials): array
    {
        return $this->packageReadmeSyncService->synchronize(
            $source['organizationId'],
            $packageName,
            fn (): ?string => $this->bitbucketPackageReadmeExtractor->extractBitbucketReadmeHtml(
                $source['owner'],
                $source['repository'],
                $defaultBranch,
                $this->credentialsOf($credentials),
            ),
        );
    }

    private function credentialsOf(object $credentials): BitbucketAccessCredentials
    {
        if (! $credentials instanceof BitbucketAccessCredentials) {
            throw new LogicException('Expected Bitbucket access credentials.');
        }

        return $credentials;
    }

    private function metadata(BitbucketPackageMetadata $bitbucketPackageMetadata): ProviderPackageMetadata
    {
        return new ProviderPackageMetadata(
            $bitbucketPackageMetadata,
            $bitbucketPackageMetadata->composer,
            $bitbucketPackageMetadata->refName,
            $bitbucketPackageMetadata->defaultBranch,
            $bitbucketPackageMetadata->commitReference,
            $bitbucketPackageMetadata->refType,
        );
    }

    private function metadataOf(ProviderPackageMetadata $providerPackageMetadata): BitbucketPackageMetadata
    {
        if (! $providerPackageMetadata->nativeMetadata instanceof BitbucketPackageMetadata) {
            throw new LogicException('Expected Bitbucket package metadata.');
        }

        return $providerPackageMetadata->nativeMetadata;
    }
}
