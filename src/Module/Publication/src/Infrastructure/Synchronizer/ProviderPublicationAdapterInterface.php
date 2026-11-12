<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Synchronizer;

use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use Packio\Publication\Infrastructure\Git\GitReference;

interface ProviderPublicationAdapterInterface
{
    public function provider(): string;

    /**
     * @param array<string, mixed> $source
     */
    public function credentials(array $source, ?string $accessToken): object;

    public function accessToken(object $credentials): ?string;

    /**
     * @param array<string, mixed> $source
     */
    public function snapshot(array $source, object $credentials): ProviderRepositorySnapshot;

    /**
     * @param array<string, mixed> $source
     */
    public function fetchMetadata(
        array $source,
        GitReference $gitReference,
        string $defaultBranch,
        object $credentials
    ): ProviderPackageMetadata;

    public function sourceUrl(ProviderPackageMetadata $providerPackageMetadata): string;

    /**
     * @param array<string, mixed> $package
     *
     * @return array<string, mixed>
     */
    public function releasePayload(
        ProviderPackageMetadata $providerPackageMetadata,
        array $package,
        string $createdAt,
        string $sourceId,
        ComposerReleaseDateMap $composerReleaseDateMap,
        string $version,
    ): array;

    /**
     * @param array<string, mixed> $source
     *
     * @return array{status: string, message: string}
     */
    public function syncReadme(
        array $source,
        string $packageName,
        string $defaultBranch,
        object $credentials,
    ): array;
}
