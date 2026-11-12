<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Storage;

use InvalidArgumentException;
use Packio\Registry\Infrastructure\Storage\ArchiveStorageConfig;
use PackioTest\Support\Base\UnitTestCase;

final class ArchiveStorageConfigTest extends UnitTestCase
{
    public function testDefaultsToLocalStorageConfig(): void
    {
        $archiveStorageConfig = ArchiveStorageConfig::fromApplicationConfig([]);

        self::assertSame('local', $archiveStorageConfig->driver);
        self::assertSame('data/package-archives', $archiveStorageConfig->local['path']);
        self::assertSame('private', $archiveStorageConfig->activePrefix());
    }

    public function testRejectsLocalStorageInsidePublicRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ArchiveStorageConfig::fromApplicationConfig([
            'storage' => [
                'archive' => [
                    'driver' => 'local',
                    'local'  => [
                        'path'   => 'public/package-archives',
                        'prefix' => 'private',
                    ],
                ],
            ],
        ]);
    }

    public function testRejectsPrefixWithSlashes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ArchiveStorageConfig::fromApplicationConfig([
            'storage' => [
                'archive' => [
                    'driver' => 'local',
                    'local'  => [
                        'path'   => 'data/package-archives',
                        'prefix' => '/private',
                    ],
                ],
            ],
        ]);
    }

    public function testRejectsIncompleteS3Config(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ArchiveStorageConfig::fromApplicationConfig([
            'storage' => [
                'archive' => [
                    'driver' => 's3',
                    's3'     => [
                        'bucket' => '',
                    ],
                ],
            ],
        ]);
    }

    public function testRejectsNonHttpsS3EndpointInProduction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ArchiveStorageConfig::fromApplicationConfig([
            'packio'  => [
                'production_mode' => true,
            ],
            'storage' => [
                'archive' => [
                    'driver' => 's3',
                    's3'     => [
                        'bucket'     => 'packio-archives',
                        'endpoint'   => 'http://objectstorage.test',
                        'access_key' => 'key',
                        'secret_key' => 'secret',
                        'prefix'     => 'private',
                    ],
                ],
            ],
        ]);
    }
}
