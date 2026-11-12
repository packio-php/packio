<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Cli;

use Packio\Registry\Application\Dto\PackageData;
use Packio\Shared\Api\Dto\OrganizationDto;

use function array_values;
use function preg_match;

/**
 * Requires $organizationApiFacade and $packageQueryPort properties on the using command.
 */
trait ArchiveCommandSupport
{
    private function resolveOrganization(string $organization): ?OrganizationDto
    {
        $resolvedBySlug = $this->organizationApiFacade->findOrganizationBySlug($organization);
        if ($resolvedBySlug instanceof OrganizationDto) {
            return $resolvedBySlug;
        }

        if (! $this->isUuid($organization)) {
            return null;
        }

        return $this->organizationApiFacade->findOrganization($organization);
    }

    private function isUuid(string $value): bool
    {
        return 1 === preg_match(
            '/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/',
            $value,
        );
    }

    /**
     * @return list<PackageData>
     */
    private function packages(string $organizationId, string $packageName): array
    {
        if ('' !== $packageName) {
            $package = $this->packageQueryPort->findPackageInOrganization($organizationId, $packageName);

            return $package instanceof PackageData ? [$package] : [];
        }

        return array_values($this->packageQueryPort->allPackages($organizationId));
    }
}
