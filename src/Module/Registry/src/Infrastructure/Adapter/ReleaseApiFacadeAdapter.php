<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Adapter;

use Packio\Registry\Application\Dto\ReleaseData;
use Packio\Registry\Application\Port\ReleaseQueryPort;
use Packio\Registry\Application\Port\ReleaseStorageMetadataPort;
use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Api\ReleaseApiFacadeInterface;

use function array_map;

final readonly class ReleaseApiFacadeAdapter implements ReleaseQueryPort, ReleaseStorageMetadataPort
{
    public function __construct(private ReleaseApiFacadeInterface $releaseApiFacade) {}

    /** @return ReleaseData[] */
    public function releasesForPackage(string $organizationId, string $packageName): array
    {
        return array_map(
            $this->mapReleaseData(...),
            $this->releaseApiFacade->releasesForPackage($packageName, false, $organizationId),
        );
    }

    public function findRelease(string $organizationId, string $packageName, string $version): ?ReleaseData
    {
        $dto = $this->releaseApiFacade->findRelease($packageName, $version, $organizationId);

        return $dto instanceof ReleaseDto ? $this->mapReleaseData($dto) : null;
    }

    public function latestActiveVersions(string $organizationId): array
    {
        return $this->releaseApiFacade->latestActiveVersions($organizationId);
    }

    public function findDistStorageMetadata(string $organizationId, string $packageName, string $version): ?array
    {
        return $this->releaseApiFacade->findDistStorageMetadata($packageName, $version, $organizationId);
    }

    public function distStorageKeysForPackage(string $organizationId, string $packageName): array
    {
        return $this->releaseApiFacade->distStorageKeysForPackage($packageName, $organizationId);
    }

    public function updateDistStorageMetadata(
        string $organizationId,
        string $packageName,
        string $version,
        array $storageData
    ): ?ReleaseData {
        $dto = $this->releaseApiFacade->updateDistStorageMetadata(
            $packageName,
            $version,
            $organizationId,
            $storageData,
        );

        return $dto instanceof ReleaseDto ? $this->mapReleaseData($dto) : null;
    }

    private function mapReleaseData(ReleaseDto $releaseDto): ReleaseData
    {
        return new ReleaseData(
            id: $releaseDto->packageName . ':' . $releaseDto->version,
            version: $releaseDto->version,
            reference: $releaseDto->reference,
            active: $releaseDto->active,
            composerJson: $releaseDto->composer,
            distMetadata: $releaseDto->dist,
            source: $releaseDto->source,
            releasedAt: $releaseDto->releasedAt,
        );
    }
}
