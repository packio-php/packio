<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Application\Storage;

use InvalidArgumentException;
use Packio\Registry\Application\Storage\PackageArchiveKeyGenerator;
use PackioTest\Support\Base\UnitTestCase;

final class PackageArchiveKeyGeneratorTest extends UnitTestCase
{
    public function testGeneratesRepmanStyleArchiveKey(): void
    {
        $packageArchiveKeyGenerator = new PackageArchiveKeyGenerator('private');

        self::assertSame(
            'private/org/018fd/dist/acme/tools/1.2.3_3f45abc.zip',
            $packageArchiveKeyGenerator->key('018fd', 'acme/tools', '1.2.3', '3f45abc'),
        );
    }

    public function testPreservesDevMasterEquivalent(): void
    {
        $packageArchiveKeyGenerator = new PackageArchiveKeyGenerator('private');

        self::assertSame(
            'private/org/org-1/dist/acme/tools/dev-master_ref.zip',
            $packageArchiveKeyGenerator->key('org-1', 'acme/tools', '9999999-dev', 'ref'),
        );
    }

    public function testHashesVersionSegmentsWithSlashes(): void
    {
        $packageArchiveKeyGenerator = new PackageArchiveKeyGenerator('private');

        self::assertStringMatchesFormat(
            'private/org/org-1/dist/acme/tools/version-%x_ref.zip',
            $packageArchiveKeyGenerator->key('org-1', 'acme/tools', 'dev-feature/storage', 'ref'),
        );
    }

    public function testGeneratesOrganizationAndPackagePrefixes(): void
    {
        $packageArchiveKeyGenerator = new PackageArchiveKeyGenerator('private');

        self::assertSame(
            'private/org/org-1/dist/',
            $packageArchiveKeyGenerator->organizationDistPrefix('org-1'),
        );
        self::assertSame(
            'private/org/org-1/dist/acme/tools/',
            $packageArchiveKeyGenerator->packageDistPrefix('org-1', 'acme/tools'),
        );
    }

    public function testRejectsNonCanonicalPackageName(): void
    {
        $packageArchiveKeyGenerator = new PackageArchiveKeyGenerator('private');

        $this->expectException(InvalidArgumentException::class);

        $packageArchiveKeyGenerator->key('org-1', 'acme', '1.0.0', 'ref');
    }
}
