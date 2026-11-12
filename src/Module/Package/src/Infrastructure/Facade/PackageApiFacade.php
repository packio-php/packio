<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Facade;

use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\Exception\PackageDeletionFailedException;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Package\Application\Command\PackageCommandService;
use Packio\Package\Application\Deletion\PackageDeletionOutcome;
use Packio\Package\Application\Deletion\PackageDeletionScheduler;
use Packio\Package\Application\Query\PackageQueryService;
use Packio\Package\Domain\Entity\Package;
use Packio\Shared\Domain\Organization\ActorContext;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function array_map;

final readonly class PackageApiFacade implements PackageApiFacadeInterface
{
    public function __construct(
        private PackageCommandService $packageCommandService,
        private PackageQueryService $packageQueryService,
        private ObjectMapperInterface $objectMapper,
        private PackageDeletionScheduler $packageDeletionScheduler,
    ) {}

    public function upsert(PackageDto $packageDto): PackageDto
    {
        $package = $this->packageCommandService->upsert(
            $packageDto->organizationId,
            $packageDto->name,
            $packageDto->type,
            $packageDto->description,
            $packageDto->visibility,
            $packageDto->sourceId,
            $packageDto->sourceType,
            $packageDto->sourceUrl,
            $packageDto->readmeHtml,
            $packageDto->active,
        );

        return $this->objectMapper->map($package, PackageDto::class);
    }

    /**
     * @return list<PackageDto>
     */
    public function allPackages(): array
    {
        return array_map(
            fn (Package $package): PackageDto => $this->objectMapper->map($package, PackageDto::class),
            $this->packageQueryService->allPackages(),
        );
    }

    public function countPackages(): int
    {
        return $this->packageQueryService->countPackages();
    }

    public function countForOrganization(string $organizationId): int
    {
        return $this->packageQueryService->countForOrganization($organizationId);
    }

    public function packagesForOrganization(string $organizationId): array
    {
        return array_map(
            fn (Package $package): PackageDto => $this->objectMapper->map($package, PackageDto::class),
            $this->packageQueryService->packagesForOrganization($organizationId),
        );
    }

    public function findPackageInOrganization(string $organizationId, string $name): ?PackageDto
    {
        $entity = $this->packageQueryService->findPackageInOrganization($organizationId, $name);

        return $entity instanceof Package
            ? $this->objectMapper->map($entity, PackageDto::class)
            : null;
    }

    public function updateReadmeHtml(string $organizationId, string $name, ?string $readmeHtml): ?PackageDto
    {
        $entity = $this->packageCommandService->updateReadmeHtml(
            $organizationId,
            $name,
            $readmeHtml,
        );

        return $entity instanceof Package
            ? $this->objectMapper->map($entity, PackageDto::class)
            : null;
    }

    public function deletePackageInContext(ActorContext $actorContext, string $name): bool
    {
        $packageDeletionResult = $this->packageCommandService->deletePackageInContext($actorContext, $name);

        return match ($packageDeletionResult->outcome) {
            PackageDeletionOutcome::Deleted        => true,
            PackageDeletionOutcome::AlreadyDeleted => false,
            default                                => throw new PackageDeletionFailedException($packageDeletionResult->message),
        };
    }

    public function deletePackage(string $organizationId, string $name): bool
    {
        return $this->packageCommandService->deletePackage($organizationId, $name);
    }

    public function schedulePackageDelete(
        string $organizationId,
        string $name,
        ?string $deletionRunId = null,
        string $origin = '',
    ): bool {
        return $this->packageDeletionScheduler->schedule($organizationId, $name, $deletionRunId, $origin);
    }

    /**
     * @return list<PackageDto>
     */
    public function updateVisibilityBySourceIdInContext(ActorContext $actorContext, string $sourceId, string $visibility): array
    {
        return array_map(
            fn (Package $package): PackageDto => $this->objectMapper->map($package, PackageDto::class),
            $this->packageCommandService->updateVisibilityBySourceIdInContext($actorContext, $sourceId, $visibility),
        );
    }

    /**
     * @return list<PackageDto>
     */
    public function updateActivityBySourceIdInContext(ActorContext $actorContext, string $sourceId, bool $active): array
    {
        return array_map(
            fn (Package $package): PackageDto => $this->objectMapper->map($package, PackageDto::class),
            $this->packageCommandService->updateActivityBySourceIdInContext($actorContext, $sourceId, $active),
        );
    }
}
