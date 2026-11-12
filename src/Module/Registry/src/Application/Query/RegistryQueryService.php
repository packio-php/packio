<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Query;

use Packio\Registry\Application\Dto\PackageData;
use Packio\Registry\Application\Dto\ReleaseData;
use Packio\Registry\Application\Dto\SourceData;
use Packio\Registry\Application\Port\PackageQueryPort;
use Packio\Registry\Application\Port\ReleaseQueryPort;
use Packio\Registry\Application\Port\SourceQueryPort;
use Packio\Registry\Application\Service\ReleaseArchiveDescriptorInterface;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function explode;
use function in_array;
use function rawurlencode;
use function sprintf;

final readonly class RegistryQueryService implements ReleaseArchiveDescriptorInterface
{
    // Providers DistArchiveFetcher can build an archive for.
    public const array ARCHIVE_PROVIDERS = ['github', 'gitlab', 'gitlab_self_hosted', 'generic_git', 'bitbucket', 'codeberg'];

    public function __construct(
        private PackageQueryPort $packageQueryPort,
        private ReleaseQueryPort $releaseQueryPort,
        private SourceQueryPort $sourceQueryPort,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function publicRegistryPayload(string $organizationId): array
    {
        $packages = array_values(
            array_filter(
                $this->packageQueryPort->allPackages($organizationId),
                static fn (PackageData $packageData): bool => $packageData->active
                    && 'public' === $packageData->visibility,
            ),
        );

        return [
            'metadata-url'       => $this->metadataPath(),
            'available-packages' => array_map(
                static fn (PackageData $packageData): string => $packageData->name,
                $packages,
            ),
            'providers-api'      => false,
            'registry'           => [
                'mode'   => 'bootstrap',
                'format' => 'p2',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function privateRegistryPayload(string $organizationId): array
    {
        $packages = array_values(
            array_filter(
                $this->packageQueryPort->allPackages($organizationId),
                static fn (PackageData $packageData): bool => $packageData->active
                    && 'private' === $packageData->visibility,
            ),
        );

        return [
            'metadata-url'       => $this->metadataPath(),
            'available-packages' => array_map(
                static fn (PackageData $packageData): string => $packageData->name,
                $packages,
            ),
            'auth'               => 'required',
        ];
    }

    /**
     * @return null|array<string, mixed>
     */
    public function packageMetadata(string $packageName, string $organizationId): ?array
    {
        if ('' === $organizationId) {
            return null;
        }

        $package = $this->packageQueryPort->findPackageInOrganization(
            $organizationId,
            $packageName,
        );
        if (! $package instanceof PackageData || ! $package->active || $package->organizationId !== $organizationId) {
            return null;
        }

        $releases = $this->releaseQueryPort->releasesForPackage(
            $organizationId,
            $package->name,
        );

        return [
            'packages' => [
                $packageName => array_map(
                    fn (ReleaseData $releaseData): array => $this->releaseMetadata(
                        $package,
                        $releaseData,
                    ),
                    $releases,
                ),
            ],
        ];
    }

    /**
     * @return null|array{provider: string, owner: string, repository: string, reference: string, source_id: string, file_name: string, base_url: string, provider_config: array<string, mixed>}
     */
    public function archiveDescriptor(string $packageName, string $version, string $organizationId): ?array
    {
        $package = $this->packageQueryPort->findPackageInOrganization(
            $organizationId,
            $packageName,
        );
        if (
            ! $package instanceof PackageData
            || ! $package->active
            || ! in_array($package->sourceType, self::ARCHIVE_PROVIDERS, true)
            || $package->organizationId !== $organizationId
        ) {
            return null;
        }

        $release = $this->releaseQueryPort->findRelease(
            $organizationId,
            $package->name,
            $version,
        );
        if (! $release instanceof ReleaseData || ! $release->active) {
            return null;
        }

        $source = '' !== $package->sourceId
            ? $this->sourceQueryPort->findSource($package->sourceId)
            : null;
        if (
            ! $source instanceof SourceData
            || $source->organizationId !== $organizationId
        ) {
            return null;
        }

        return [
            'provider'        => $source->provider,
            'owner'           => $source->owner,
            'repository'      => $source->repository,
            'reference'       => $release->reference,
            'source_id'       => $source->id,
            'file_name'       => sprintf(
                '%s-%s.zip',
                $source->repository,
                $release->reference,
            ),
            'base_url'        => $source->providerConfig['base_url'] ?? '',
            'provider_config' => [
                ...$source->providerConfig,
                'auth_strategy' => $source->authType,
            ],
        ];
    }

    /**
     * @return list<array{
     *     name: string,
     *     description: null|string,
     *     latest_version: null|string,
     *     visibility: string,
     *     active: bool,
     *     source_url: string,
     *     registry_endpoint: string,
     *     access_hint: string
     * }>
     */
    public function packageOverview(string $organizationId): array
    {
        $versions = $this->releaseQueryPort->latestActiveVersions($organizationId);

        return array_map(function(PackageData $packageData) use ($versions): array {
            $isPrivate = 'private' === $packageData->visibility;

            return [
                'name'              => $packageData->name,
                'description'       => $packageData->description ?? '',
                'latest_version'    => $versions[$packageData->name] ?? null,
                'visibility'        => $packageData->visibility,
                'active'            => $packageData->active,
                'source_url'        => $packageData->sourceUrl,
                'registry_endpoint' => $isPrivate
                    ? $this->privatePackagesPath()
                    : $this->publicPackagesPath(),
                'access_hint'       => $packageData->active
                    ? ($isPrivate
                        ? 'Requires registry token.'
                        : 'Available without authentication.')
                    : 'Hidden from registry.',
            ];
        }, array_values(
            $this->packageQueryPort->allPackages($organizationId),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseMetadata(PackageData $packageData, ReleaseData $releaseData): array
    {
        return array_merge(
            $releaseData->composerJson ?? [],
            [
                'name'        => $packageData->name,
                'description' => $packageData->description,
                'version'     => $releaseData->version,
                'type'        => $packageData->type,
                'source'      => $releaseData->source,
                'dist'        => $this->normalizeDist(
                    $packageData,
                    $releaseData->version,
                    $releaseData->reference,
                    $releaseData->distMetadata ?? [],
                ),
                'require'     => (array) ($releaseData->composerJson['require'] ?? []),
            ],
            null !== $releaseData->releasedAt && '' !== $releaseData->releasedAt
                ? [
                    'time' => $releaseData->releasedAt,
                ]
                : [],
        );
    }

    /**
     * @param array<string, mixed> $dist
     *
     * @return array<string, mixed>
     */
    private function normalizeDist(PackageData $packageData, string $version, string $releaseReference, array $dist): array
    {
        if (! in_array($packageData->sourceType, self::ARCHIVE_PROVIDERS, true)) {
            return $dist;
        }

        [$vendor, $packageName] = explode('/', $packageData->name, 2);
        $reference              = (string) ($dist['reference'] ?? '');
        if ('' === $reference) {
            $reference         = $releaseReference;
            $dist['reference'] = $reference;
        }

        $dist['url'] = sprintf(
            '/dist/%s/%s/%s/%s.zip',
            $vendor,
            $packageName,
            rawurlencode($version),
            rawurlencode($reference),
        );

        return $dist;
    }

    private function metadataPath(): string
    {
        return '/p2/%package%';
    }

    private function publicPackagesPath(): string
    {
        return '/packages.json';
    }

    private function privatePackagesPath(): string
    {
        return '/private/packages.json';
    }
}
