<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Adapter;

use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Publication\Application\Port\SynchronizerDependenciesPort;
use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Api\SourceSecretResolverInterface;

final readonly class PublicationSynchronizationAdapter implements SynchronizerDependenciesPort
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourceSecretResolverInterface $sourceSecretResolver,
        private PackageApiFacadeInterface $packageApiFacade,
        private ReleaseApiFacadeInterface $releaseApiFacade,
        private AuditApiFacadeInterface $auditApiFacade,
    ) {}

    public function findSource(string $sourceId): ?array
    {
        $source = $this->sourceApiFacade->findSource($sourceId);

        return $source instanceof SourceDto
            ? $this->sourceToArray($source)
            : null;
    }

    public function resolveSourceAuthorizationSecret(string $sourceId): ?string
    {
        return $this->sourceSecretResolver->resolveSourceAuthorizationSecret($sourceId);
    }

    public function markSyncSucceeded(string $sourceId): ?array
    {
        $source = $this->sourceApiFacade->markSyncSucceeded($sourceId);

        return $source instanceof SourceDto
            ? $this->sourceToArray($source)
            : null;
    }

    public function markSyncFailed(string $sourceId, string $error): ?array
    {
        $source = $this->sourceApiFacade->markSyncFailed($sourceId, $error);

        return $source instanceof SourceDto
            ? $this->sourceToArray($source)
            : null;
    }

    public function findPackageInOrganization(string $organizationId, string $name): ?array
    {
        $package = $this->packageApiFacade->findPackageInOrganization($organizationId, $name);

        return $package instanceof PackageDto
            ? $this->packageToArray($package)
            : null;
    }

    public function upsertPackage(array $packageData): array
    {
        return $this->packageToArray(
            $this->packageApiFacade->upsert($this->arrayToPackage($packageData)),
        );
    }

    public function updatePackageReadmeHtml(string $organizationId, string $name, ?string $readmeHtml): ?array
    {
        $package = $this->packageApiFacade->updateReadmeHtml($organizationId, $name, $readmeHtml);

        return $package instanceof PackageDto
            ? $this->packageToArray($package)
            : null;
    }

    public function publishRelease(array $releaseData): array
    {
        return $this->releaseToArray(
            $this->releaseApiFacade->publish($this->arrayToRelease($releaseData)),
        );
    }

    public function applyReleaseRetention(
        string $organizationId,
        string $sourceId,
        string $packageName,
        int $keepLastReleases
    ): array {
        return $this->releaseApiFacade
            ->applyRetentionForSourcePackage(
                $organizationId,
                $sourceId,
                $packageName,
                $keepLastReleases,
            )
            ->toArray()
        ;
    }

    public function recordAudit(
        string $event,
        array $context,
        string $organizationId,
        string $sourceId = '',
        string $packageName = '',
    ): void {
        $this->auditApiFacade->record(
            $event,
            $context,
            $organizationId,
            $sourceId,
            $packageName,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceToArray(SourceDto $sourceDto): array
    {
        return [
            'id'                      => $sourceDto->id,
            'provider'                => $sourceDto->provider,
            'type'                    => $sourceDto->type,
            'displayName'             => $sourceDto->displayName,
            'canonicalUrl'            => $sourceDto->canonicalUrl,
            'url'                     => $sourceDto->url,
            'owner'                   => $sourceDto->owner,
            'repository'              => $sourceDto->repository,
            'defaultRef'              => $sourceDto->defaultRef,
            'organizationId'          => $sourceDto->organizationId,
            'visibility'              => $sourceDto->visibility,
            'keepLastReleases'        => $sourceDto->keepLastReleases,
            'active'                  => $sourceDto->active,
            'lastSyncStatus'          => $sourceDto->lastSyncStatus,
            'lastSuccessfulSyncAt'    => $sourceDto->lastSuccessfulSyncAt,
            'lastFailedSyncAt'        => $sourceDto->lastFailedSyncAt,
            'lastSyncError'           => $sourceDto->lastSyncError,
            'archived'                => $sourceDto->archived,
            'archivedAt'              => $sourceDto->archivedAt,
            'status'                  => $sourceDto->status,
            'createdAt'               => $sourceDto->createdAt,
            'providerAuthorizationId' => $sourceDto->providerAuthorizationId,
            'providerResourceId'      => $sourceDto->providerResourceId,
            'providerConfig'          => $sourceDto->providerConfig,
            'authType'                => $sourceDto->authType,
            'credentialsConfigured'   => $sourceDto->credentialsConfigured,
            'authorizationStatus'     => $sourceDto->authorizationStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packageToArray(PackageDto $packageDto): array
    {
        return [
            'name'           => $packageDto->name,
            'type'           => $packageDto->type,
            'description'    => $packageDto->description,
            'visibility'     => $packageDto->visibility,
            'sourceType'     => $packageDto->sourceType,
            'sourceUrl'      => $packageDto->sourceUrl,
            'createdAt'      => $packageDto->createdAt,
            'updatedAt'      => $packageDto->updatedAt,
            'organizationId' => $packageDto->organizationId,
            'active'         => $packageDto->active,
            'readmeHtml'     => $packageDto->readmeHtml,
            'sourceId'       => $packageDto->sourceId,
            'downloadCount'  => $packageDto->downloadCount,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function arrayToPackage(array $data): PackageDto
    {
        return new PackageDto(
            name: (string) ($data['name'] ?? ''),
            type: (string) ($data['type'] ?? 'library'),
            description: (string) ($data['description'] ?? ''),
            visibility: (string) ($data['visibility'] ?? 'public'),
            sourceType: (string) ($data['sourceType'] ?? ''),
            sourceUrl: (string) ($data['sourceUrl'] ?? ''),
            createdAt: (string) ($data['createdAt'] ?? ''),
            updatedAt: (string) ($data['updatedAt'] ?? ''),
            organizationId: (string) ($data['organizationId'] ?? ''),
            active: (bool) ($data['active'] ?? true),
            readmeHtml: isset($data['readmeHtml'])
                ? (string) $data['readmeHtml']
                : null,
            sourceId: (string) ($data['sourceId'] ?? ''),
            downloadCount: (int) ($data['downloadCount'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseToArray(ReleaseDto $releaseDto): array
    {
        return [
            'packageName'       => $releaseDto->packageName,
            'version'           => $releaseDto->version,
            'reference'         => $releaseDto->reference,
            'composer'          => $releaseDto->composer,
            'source'            => $releaseDto->source,
            'dist'              => $releaseDto->dist,
            'createdAt'         => $releaseDto->createdAt,
            'releasedAt'        => $releaseDto->releasedAt,
            'organizationId'    => $releaseDto->organizationId,
            'active'            => $releaseDto->active,
            'downloadCount'     => $releaseDto->downloadCount,
            'upstreamReference' => $releaseDto->upstreamReference,
            'referenceType'     => $releaseDto->referenceType,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function arrayToRelease(array $data): ReleaseDto
    {
        return new ReleaseDto(
            packageName: (string) ($data['packageName'] ?? ''),
            version: (string) ($data['version'] ?? ''),
            reference: (string) ($data['reference'] ?? ''),
            composer: (array) ($data['composer'] ?? []),
            source: (array) ($data['source'] ?? []),
            dist: (array) ($data['dist'] ?? []),
            createdAt: (string) ($data['createdAt'] ?? ''),
            organizationId: (string) ($data['organizationId'] ?? ''),
            releasedAt: isset($data['releasedAt'])
                ? (string) $data['releasedAt']
                : (isset($data['released_at']) ? (string) $data['released_at'] : null),
            active: (bool) ($data['active'] ?? true),
            downloadCount: (int) ($data['downloadCount'] ?? 0),
            upstreamReference: isset($data['upstreamReference'])
                ? (string) $data['upstreamReference']
                : (isset($data['upstream_reference']) ? (string) $data['upstream_reference'] : null),
            referenceType: isset($data['referenceType'])
                ? (string) $data['referenceType']
                : (isset($data['reference_type']) ? (string) $data['reference_type'] : null),
        );
    }
}
