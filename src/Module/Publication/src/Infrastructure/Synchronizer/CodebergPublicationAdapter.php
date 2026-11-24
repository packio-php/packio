<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Synchronizer;

use LogicException;
use Packio\Publication\Application\Service\PackageReadmeSyncService;
use Packio\Publication\Infrastructure\Codeberg\CodebergAccessCredentials;
use Packio\Publication\Infrastructure\Codeberg\CodebergComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\Codeberg\CodebergPackageMetadata;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Readme\CodebergPackageReadmeExtractorInterface;

use function is_string;
use function rawurlencode;
use function sprintf;

final readonly class CodebergPublicationAdapter implements ProviderPublicationAdapterInterface
{
    public function __construct(
        private CodebergComposerMetadataFetcherInterface $codebergComposerMetadataFetcher,
        private CodebergPackageReadmeExtractorInterface $codebergPackageReadmeExtractor,
        private PackageReadmeSyncService $packageReadmeSyncService,
        private string $provider = 'codeberg',
    ) {}

    public function provider(): string
    {
        return $this->provider;
    }

    public function credentials(array $source, ?string $accessToken): object
    {
        $baseUrl = $source['providerConfig']['base_url'] ?? null;

        // Only self-hosted sources store base_url; Codeberg keeps the default host.
        return is_string($baseUrl)
            ? new CodebergAccessCredentials($accessToken, $baseUrl)
            : new CodebergAccessCredentials($accessToken);
    }

    public function accessToken(object $credentials): ?string
    {
        return $this->credentialsOf($credentials)->token;
    }

    public function snapshot(array $source, object $credentials): ProviderRepositorySnapshot
    {
        $snapshot = $this->codebergComposerMetadataFetcher->snapshot(
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
        $codebergPackageMetadata = $this->codebergComposerMetadataFetcher->fetchReference(
            $source['owner'],
            $source['repository'],
            $gitReference,
            $defaultBranch,
            $this->credentialsOf($credentials),
        );

        return $this->metadata($codebergPackageMetadata);
    }

    public function sourceUrl(ProviderPackageMetadata $providerPackageMetadata): string
    {
        $codebergPackageMetadata = $this->metadataOf($providerPackageMetadata);

        return sprintf(
            '%s/%s/%s',
            $codebergPackageMetadata->baseUrl,
            $codebergPackageMetadata->owner,
            $codebergPackageMetadata->repository,
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
        $codebergPackageMetadata = $this->metadataOf($providerPackageMetadata);

        return [
            'packageName'       => $package['name'],
            'version'           => $version,
            'reference'         => $codebergPackageMetadata->commitReference,
            'upstreamReference' => $codebergPackageMetadata->refName,
            'referenceType'     => $codebergPackageMetadata->refType,
            'composer'          => $codebergPackageMetadata->composer,
            'source'            => [
                'source_id' => $sourceId,
                'type'      => 'git',
                'url'       => sprintf(
                    '%s/%s/%s.git',
                    $codebergPackageMetadata->baseUrl,
                    $codebergPackageMetadata->owner,
                    $codebergPackageMetadata->repository,
                ),
                'reference' => $codebergPackageMetadata->commitReference,
            ],
            'dist'              => [
                'type'      => 'zip',
                'url'       => sprintf(
                    '%s/%s/%s/archive/%s.zip',
                    $codebergPackageMetadata->baseUrl,
                    $codebergPackageMetadata->owner,
                    $codebergPackageMetadata->repository,
                    rawurlencode($codebergPackageMetadata->commitReference),
                ),
                'reference' => $codebergPackageMetadata->commitReference,
            ],
            'createdAt'         => $createdAt,
            'releasedAt'        => $composerReleaseDateMap->find($version, $codebergPackageMetadata->refName),
            'organizationId'    => $package['organizationId'],
        ];
    }

    public function syncReadme(array $source, string $packageName, string $defaultBranch, object $credentials): array
    {
        return $this->packageReadmeSyncService->synchronize(
            $source['organizationId'],
            $packageName,
            fn (): ?string => $this->codebergPackageReadmeExtractor->extractCodebergReadmeHtml(
                $source['owner'],
                $source['repository'],
                $defaultBranch,
                $this->credentialsOf($credentials),
            ),
        );
    }

    private function credentialsOf(object $credentials): CodebergAccessCredentials
    {
        if (! $credentials instanceof CodebergAccessCredentials) {
            throw new LogicException('Expected Codeberg access credentials.');
        }

        return $credentials;
    }

    private function metadata(CodebergPackageMetadata $codebergPackageMetadata): ProviderPackageMetadata
    {
        return new ProviderPackageMetadata(
            $codebergPackageMetadata,
            $codebergPackageMetadata->composer,
            $codebergPackageMetadata->refName,
            $codebergPackageMetadata->defaultBranch,
            $codebergPackageMetadata->commitReference,
            $codebergPackageMetadata->refType,
        );
    }

    private function metadataOf(ProviderPackageMetadata $providerPackageMetadata): CodebergPackageMetadata
    {
        if (! $providerPackageMetadata->nativeMetadata instanceof CodebergPackageMetadata) {
            throw new LogicException('Expected Codeberg package metadata.');
        }

        return $providerPackageMetadata->nativeMetadata;
    }
}
