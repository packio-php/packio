<?php

declare(strict_types=1);

namespace Packio\Release\Infrastructure\Facade;

use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Api\Dto\ReleaseRetentionResultDto;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Release\Application\Command\ReleaseCommandService;
use Packio\Release\Application\Query\ReleaseQueryService;
use Packio\Release\Application\Service\ReleaseRetentionService;
use Packio\Release\Domain\Entity\Release;
use Packio\Release\Domain\ValueObject\ReleaseDistStorageMetadata;
use Packio\Release\Infrastructure\Mapper\ReleaseDtoMapper;
use Packio\Shared\Domain\Organization\ActorContext;

use function array_map;
use function is_array;

final readonly class ReleaseApiFacade implements ReleaseApiFacadeInterface
{
    public function __construct(
        private ReleaseCommandService $releaseCommandService,
        private ReleaseQueryService $releaseQueryService,
        private ReleaseDtoMapper $releaseDtoMapper,
        private ReleaseRetentionService $releaseRetentionService,
    ) {}

    public function publish(ReleaseDto $releaseDto): ReleaseDto
    {
        $release = $this->releaseCommandService->publish(
            $releaseDto->organizationId,
            $releaseDto->packageName,
            $releaseDto->version,
            $releaseDto->reference,
            $releaseDto->composer,
            $releaseDto->source,
            $releaseDto->dist,
            $releaseDto->releasedAt,
            $releaseDto->upstreamReference,
            $releaseDto->referenceType,
        );

        return $this->releaseDtoMapper->map($release);
    }

    /**
     * @return list<ReleaseDto>
     */
    public function releasesForPackage(string $packageName, bool $includeInactive, string $organizationId): array
    {
        return array_map(
            $this->releaseDtoMapper->map(...),
            $this->releaseQueryService->releasesForPackage($packageName, $includeInactive, $organizationId),
        );
    }

    public function latestActiveVersions(string $organizationId): array
    {
        return $this->releaseQueryService->latestActiveVersions($organizationId);
    }

    public function findRelease(string $packageName, string $version, string $organizationId): ?ReleaseDto
    {
        $entity = $this->releaseQueryService->findRelease($packageName, $version, $organizationId);

        return $entity instanceof Release
            ? $this->releaseDtoMapper->map($entity)
            : null;
    }

    public function updateActivityInContext(ActorContext $actorContext, string $packageName, string $version, bool $active): ?ReleaseDto
    {
        $entity = $this->releaseCommandService->updateActivityInContext($actorContext, $packageName, $version, $active);

        return $entity instanceof Release
            ? $this->releaseDtoMapper->map($entity)
            : null;
    }

    public function applyRetentionForSourcePackage(
        string $organizationId,
        string $sourceId,
        string $packageName,
        int $keepLastReleases
    ): ReleaseRetentionResultDto {
        return $this->releaseRetentionService->applyForSourcePackage(
            $organizationId,
            $sourceId,
            $packageName,
            $keepLastReleases,
        );
    }

    public function deleteReleasesForPackageInContext(ActorContext $actorContext, string $packageName): int
    {
        return $this->releaseCommandService->deleteReleasesForPackageInContext(
            $actorContext,
            $packageName,
        );
    }

    public function deleteReleasesForPackage(string $packageName, string $organizationId): int
    {
        return $this->releaseCommandService->deleteReleasesForPackage($packageName, $organizationId);
    }

    public function incrementDownloadCount(string $packageName, string $version, string $organizationId): bool
    {
        return $this->releaseCommandService->incrementDownloadCount($packageName, $version, $organizationId);
    }

    public function findDistStorageMetadata(string $packageName, string $version, string $organizationId): ?array
    {
        $entity = $this->releaseQueryService->findRelease($packageName, $version, $organizationId);
        if (! $entity instanceof Release) {
            return null;
        }

        $storageData = $entity->distData()['storage'] ?? null;
        if (! is_array($storageData)) {
            return null;
        }

        return ReleaseDistStorageMetadata::fromArray($storageData)->toArray();
    }

    public function distStorageKeysForPackage(string $packageName, string $organizationId): array
    {
        return $this->releaseQueryService->distStorageKeysForPackage($packageName, $organizationId);
    }

    public function updateDistStorageMetadata(string $packageName, string $version, string $organizationId, array $storageData): ?ReleaseDto
    {
        $entity = $this->releaseCommandService->updateDistStorageMetadata(
            $packageName,
            $version,
            $organizationId,
            $storageData,
        );

        return $entity instanceof Release
            ? $this->releaseDtoMapper->map($entity)
            : null;
    }
}
