<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Storage;

use Laminas\ServiceManager\ServiceManager;
use Packio\Registry\Infrastructure\Storage\PackageArchiveStorageFactory;
use PackioTest\Support\Base\UnitTestCase;
use RuntimeException;

use function getcwd;
use function is_dir;
use function rmdir;
use function uniqid;

final class PackageArchiveStorageFactoryTest extends UnitTestCase
{
    public function testRejectsCanonicalLocalPathInsidePublicRoot(): void
    {
        $directoryName = uniqid('packio-archive-test-', true);
        $path          = 'data/../public/' . $directoryName;
        $absolutePath  = getcwd() . '/public/' . $directoryName;

        try {
            $packageArchiveStorageFactory = new PackageArchiveStorageFactory();

            $this->expectException(RuntimeException::class);

            $packageArchiveStorageFactory(new ServiceManager([
                'services' => [
                    'config' => [
                        'storage' => [
                            'archive' => [
                                'driver' => 'local',
                                'local'  => [
                                    'path'   => $path,
                                    'prefix' => 'private',
                                ],
                            ],
                        ],
                    ],
                ],
            ]));
        } finally {
            if (is_dir($absolutePath)) {
                rmdir($absolutePath);
            }
        }
    }
}
