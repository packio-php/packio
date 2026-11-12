<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Adapter;

use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Infrastructure\Adapter\PackageApiFacadeAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PackageApiFacadeAdapter::class)]
final class PackageApiFacadeAdapterTest extends TestCase
{
    public function testAllPackagesQueriesOnlyRequestedOrganization(): void
    {
        $facade = $this->createMock(PackageApiFacadeInterface::class);
        $facade->expects(self::once())->method('packagesForOrganization')->with('org-1')->willReturn([
            $this->dto('acme/b'),
            $this->dto('acme/a'),
        ]);
        $facade->expects(self::never())->method('allPackages');

        $packages = new PackageApiFacadeAdapter($facade)->allPackages('org-1');

        self::assertCount(2, $packages);
        self::assertSame(['acme/b', 'acme/a'], [$packages[0]->name, $packages[1]->name]);
        self::assertSame('acme/b', $packages[0]->id);
        self::assertSame('acme/a', $packages[1]->id);
    }

    private function dto(string $name): PackageDto
    {
        return new PackageDto($name, 'library', '', 'private', 'github', 'https://example.test', '2026-01-01', '2026-01-01', 'org-1');
    }
}
