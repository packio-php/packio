<?php

declare(strict_types=1);

namespace Packio\Package\Application\Command;

use Packio\Package\Application\Deletion\PackageDeletionResult;
use Packio\Package\Application\Deletion\PackageDeletionService;
use Packio\Package\Application\Port\ReleaseServicePort;
use Packio\Package\Domain\Entity\Package;
use Packio\Package\Domain\Exception\PackageSourceConflictException;
use Packio\Package\Domain\Repository\PackageRepositoryInterface;
use Packio\Shared\Domain\Organization\ActorContext;
use RuntimeException;

use function sprintf;
use function trim;

final readonly class PackageCommandService
{
    public function __construct(
        private PackageRepositoryInterface $packageRepository,
        private PackageMaintenanceService $packageMaintenanceService,
        private ReleaseServicePort $releaseServicePort,
        private PackageDeletionService $packageDeletionService,
        private DownloadCountTransactionInterface $downloadCountTransaction,
    ) {}

    public function upsert(
        string $organizationId,
        string $name,
        string $type,
        string $description,
        string $visibility,
        string $sourceId,
        string $sourceType,
        string $sourceUrl,
        ?string $readmeHtml = null,
        bool $active = true
    ): Package {
        $resolvedOrganizationId = trim($organizationId);
        if ('' === $resolvedOrganizationId) {
            throw new RuntimeException('Organization is required to persist packages.');
        }

        $resolvedSourceId = trim($sourceId);
        $package          = Package::create(
            $resolvedOrganizationId,
            $name,
            $type,
            $description,
            $visibility,
            $resolvedSourceId,
            $sourceType,
            $sourceUrl,
            $readmeHtml,
            $active,
        );

        try {
            return $this->packageRepository->upsertByNaturalKey($package);
        } catch (PackageSourceConflictException) {
            throw new RuntimeException(sprintf(
                'Package "%s" is already published from another source. Use a unique composer.json name or remove the existing package/source before syncing this repository.',
                $name,
            ));
        }
    }

    public function updateReadmeHtml(string $organizationId, string $name, ?string $readmeHtml): ?Package
    {
        $entity = $this->packageRepository->findByName($name, $organizationId);
        if (! $entity instanceof Package) {
            return null;
        }

        $entity->updateReadmeHtml($readmeHtml);

        return $this->packageRepository->save($entity);
    }

    public function deletePackageInContext(ActorContext $actorContext, string $name): PackageDeletionResult
    {
        $this->assertOwnerContext($actorContext);

        return $this->packageDeletionService->delete($actorContext->organizationId, $name, '', 'api');
    }

    public function deletePackage(string $organizationId, string $name): bool
    {
        return $this->packageMaintenanceService->deletePackage($organizationId, $name);
    }

    /**
     * @return list<Package>
     */
    public function updateVisibilityBySourceIdInContext(ActorContext $actorContext, string $sourceId, string $visibility): array
    {
        $this->assertOwnerContext($actorContext);

        return $this->packageMaintenanceService->updateVisibilityBySourceId(
            $sourceId,
            $visibility,
            $actorContext->organizationId,
        );
    }

    /**
     * @return list<Package>
     */
    public function updateActivityBySourceIdInContext(ActorContext $actorContext, string $sourceId, bool $active): array
    {
        $this->assertOwnerContext($actorContext);

        return $this->packageMaintenanceService->updateActivityBySourceId(
            $sourceId,
            $active,
            $actorContext->organizationId,
        );
    }

    public function incrementDownloadCount(string $organizationId, string $name, string $version): bool
    {
        return $this->downloadCountTransaction->run(function() use ($organizationId, $name, $version): bool {
            if (! $this->packageRepository->incrementDownloadCount($organizationId, $name)) {
                return false;
            }

            $this->releaseServicePort->incrementDownloadCount($name, $version, $organizationId);

            return true;
        });
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
}
