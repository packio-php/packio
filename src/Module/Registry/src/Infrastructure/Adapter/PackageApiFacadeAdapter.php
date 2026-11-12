<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Adapter;

use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Application\Dto\PackageData;
use Packio\Registry\Application\Port\PackageQueryPort;

use function array_map;

final readonly class PackageApiFacadeAdapter implements PackageQueryPort
{
    public function __construct(private PackageApiFacadeInterface $packageApiFacade) {}

    /** @return PackageData[] */
    public function allPackages(string $organizationId): array
    {
        return array_map($this->mapPackageData(...), $this->packageApiFacade->packagesForOrganization($organizationId));
    }

    public function findPackageInOrganization(string $organizationId, string $name): ?PackageData
    {
        $dto = $this->packageApiFacade->findPackageInOrganization($organizationId, $name);

        return $dto instanceof PackageDto ? $this->mapPackageData($dto) : null;
    }

    private function mapPackageData(PackageDto $packageDto): PackageData
    {
        return new PackageData(
            id: $packageDto->name,
            name: $packageDto->name,
            type: $packageDto->type,
            sourceId: $packageDto->sourceId,
            organizationId: $packageDto->organizationId,
            active: $packageDto->active,
            visibility: $packageDto->visibility,
            description: $packageDto->description,
            sourceType: $packageDto->sourceType,
            sourceUrl: $packageDto->sourceUrl,
        );
    }
}
