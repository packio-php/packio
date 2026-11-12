<?php

declare(strict_types=1);

namespace PackioTest\Integration\Package\Infrastructure\Persistence\Cycle;

use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Shared\Domain\Organization\ActorContext;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

use function bin2hex;
use function random_bytes;

final class PackageUpsertVisibilityTest extends IntegrationTestCase
{
    #[Test]
    public function upsertKeepsVisibilityChangedBySourceSettings(): void
    {
        $organizationId = 'org-' . bin2hex(random_bytes(6));
        $packages       = $this->packages();
        $packages->upsert($this->dto($organizationId, 'Old'));

        $packages->updateVisibilityBySourceIdInContext(new ActorContext($organizationId, 'user-1', 'owner'), 'source-1', 'private');
        $packageDto = $packages->upsert($this->dto($organizationId, 'New'));

        self::assertSame('private', $packageDto->visibility);
        self::assertSame('New', $packageDto->description);
        $stored = $packages->findPackageInOrganization($organizationId, 'vendor/package');
        self::assertSame('private', $stored?->visibility);
        self::assertSame('New', $stored->description);
    }

    #[Test]
    public function insertUsesCandidateActivity(): void
    {
        $organizationId = 'org-' . bin2hex(random_bytes(6));
        $packages       = $this->packages();

        $packages->upsert($this->dto($organizationId, 'Old', false));

        self::assertFalse($packages->findPackageInOrganization($organizationId, 'vendor/package')?->active);
    }

    private function dto(string $organizationId, string $description, bool $active = true): PackageDto
    {
        return new PackageDto(
            name: 'vendor/package',
            type: 'library',
            description: $description,
            visibility: 'public',
            sourceType: 'github',
            sourceUrl: 'https://github.com/vendor/package',
            createdAt: '',
            updatedAt: '',
            organizationId: $organizationId,
            active: $active,
            sourceId: 'source-1',
        );
    }

    private function packages(): PackageApiFacadeInterface
    {
        $packages = $this->getService(PackageApiFacadeInterface::class);
        if (! $packages instanceof PackageApiFacadeInterface) {
            throw new RuntimeException('PackageApiFacadeInterface is not configured.');
        }

        return $packages;
    }
}
