<?php

declare(strict_types=1);

namespace Packio\Release\Api;

use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Api\Dto\ReleaseRetentionResultDto;
use Packio\Shared\Domain\Organization\ActorContext;

interface ReleaseApiFacadeInterface
{
    public function publish(ReleaseDto $releaseDto): ReleaseDto;

    /**
     * @return list<ReleaseDto>
     */
    public function releasesForPackage(string $packageName, bool $includeInactive, string $organizationId): array;

    /**
     * @return array<string, string> package name => version
     */
    public function latestActiveVersions(string $organizationId): array;

    public function findRelease(string $packageName, string $version, string $organizationId): ?ReleaseDto;

    public function updateActivityInContext(ActorContext $actorContext, string $packageName, string $version, bool $active): ?ReleaseDto;

    public function applyRetentionForSourcePackage(
        string $organizationId,
        string $sourceId,
        string $packageName,
        int $keepLastReleases
    ): ReleaseRetentionResultDto;

    public function deleteReleasesForPackageInContext(ActorContext $actorContext, string $packageName): int;

    public function deleteReleasesForPackage(string $packageName, string $organizationId): int;

    public function incrementDownloadCount(string $packageName, string $version, string $organizationId): bool;

    /**
     * @return null|array<string, mixed>
     */
    public function findDistStorageMetadata(string $packageName, string $version, string $organizationId): ?array;

    /**
     * @return list<string>
     */
    public function distStorageKeysForPackage(string $packageName, string $organizationId): array;

    /**
     * @param array<string, mixed> $storageData
     */
    public function updateDistStorageMetadata(
        string $packageName,
        string $version,
        string $organizationId,
        array $storageData
    ): ?ReleaseDto;
}
