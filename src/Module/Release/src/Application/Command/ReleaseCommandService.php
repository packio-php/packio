<?php

declare(strict_types=1);

namespace Packio\Release\Application\Command;

use Cake\Chronos\Chronos;
use InvalidArgumentException;
use Packio\Release\Domain\Entity\Release;
use Packio\Release\Domain\Repository\ReleaseRepositoryInterface;
use Packio\Release\Domain\ValueObject\ReleaseDistStorageMetadata;
use Packio\Shared\Domain\Organization\ActorContext;
use RuntimeException;

use function trim;

final readonly class ReleaseCommandService
{
    public function __construct(
        private ReleaseRepositoryInterface $releaseRepository,
        private ReleaseMaintenanceService $releaseMaintenanceService
    ) {}

    /**
     * @param array<string, mixed> $composer
     * @param array<string, mixed> $source
     * @param array<string, mixed> $dist
     */
    public function publish(
        string $organizationId,
        string $packageName,
        string $version,
        string $reference,
        array $composer,
        array $source,
        array $dist,
        ?string $releasedAt = null,
        ?string $upstreamReference = null,
        ?string $referenceType = null,
    ): Release {
        $resolvedOrganizationId = trim($organizationId);
        if ('' === $resolvedOrganizationId) {
            throw new RuntimeException('Organization is required to persist releases.');
        }

        return $this->releaseRepository->upsertByNaturalKey(Release::create(
            $resolvedOrganizationId,
            $packageName,
            $version,
            $reference,
            $composer,
            $source,
            $dist,
            $this->parseReleasedAt($releasedAt),
            $upstreamReference,
            $referenceType,
        ));
    }

    public function updateActivityInContext(ActorContext $actorContext, string $packageName, string $version, bool $active): ?Release
    {
        $this->assertOwnerContext($actorContext);

        return $this->releaseMaintenanceService->updateActivity(
            $packageName,
            $version,
            $active,
            $actorContext->organizationId,
        );
    }

    public function deleteReleasesForPackageInContext(ActorContext $actorContext, string $packageName): int
    {
        $this->assertOwnerContext($actorContext);

        return $this->releaseMaintenanceService->deleteReleasesForPackage(
            $packageName,
            $actorContext->organizationId,
        );
    }

    public function deleteReleasesForPackage(string $packageName, string $organizationId): int
    {
        return $this->releaseMaintenanceService->deleteReleasesForPackage(
            $packageName,
            $organizationId,
        );
    }

    public function incrementDownloadCount(string $packageName, string $version, string $organizationId): bool
    {
        return $this->releaseRepository->incrementDownloadCount($organizationId, $packageName, $version);
    }

    /**
     * @param array<string, mixed> $storageData
     */
    public function updateDistStorageMetadata(string $packageName, string $version, string $organizationId, array $storageData): ?Release
    {
        $resolvedOrganizationId = trim($organizationId);
        if ('' === $resolvedOrganizationId) {
            throw new RuntimeException('Organization is required to update release archive storage metadata.');
        }

        $releaseDistStorageMetadata  = ReleaseDistStorageMetadata::fromArray($storageData);
        $sourceReference             = trim((string) $releaseDistStorageMetadata->sourceReference);
        if ('' === $sourceReference) {
            throw new InvalidArgumentException('Release dist storage source reference is required.');
        }

        return $this->releaseRepository->updateDistStorageMetadataByNaturalKey(
            $resolvedOrganizationId,
            $packageName,
            $version,
            $sourceReference,
            $releaseDistStorageMetadata->toArray(),
        );
    }

    private function assertOwnerContext(ActorContext $actorContext): void
    {
        if (
            '' === trim($actorContext->organizationId)
            || '' === trim($actorContext->actorId)
            || ! $actorContext->isOwner()
        ) {
            throw new RuntimeException('Owner actor context is required.');
        }
    }

    private function parseReleasedAt(?string $releasedAt): ?Chronos
    {
        $value = trim((string) $releasedAt);
        if ('' === $value) {
            return null;
        }

        return Chronos::parse($value)->setTimezone('UTC');
    }
}
