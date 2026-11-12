<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Application\Storage;

use Cake\Chronos\Chronos;
use InvalidArgumentException;
use Packio\Registry\Application\Storage\ReleaseArchiveStorageMetadata;
use Packio\Registry\Application\Storage\StoredArchive;
use PackioTest\Support\Base\UnitTestCase;

final class ReleaseArchiveStorageMetadataTest extends UnitTestCase
{
    public function testBuildsPersistableStorageMetadataFromStoredArchive(): void
    {
        $storedAt = new Chronos('2026-06-10T12:00:00+00:00');

        $releaseArchiveStorageMetadata = ReleaseArchiveStorageMetadata::fromStoredArchive(
            'local',
            new StoredArchive(
                key: 'private/org/org-1/dist/vendor/package/1.0.0_ref.zip',
                bucket: null,
                size: 13,
                sha256: 'abc123',
                etag: '"abc123"',
                contentType: 'application/zip',
                storedAt: $storedAt,
            ),
            'github',
            'ref',
        );

        self::assertSame([
            'driver'           => 'local',
            'bucket'           => null,
            'key'              => 'private/org/org-1/dist/vendor/package/1.0.0_ref.zip',
            'content_type'     => 'application/zip',
            'size'             => 13,
            'sha256'           => 'abc123',
            'etag'             => '"abc123"',
            'stored_at'        => '2026-06-10T12:00:00+00:00',
            'source_provider'  => 'github',
            'source_reference' => 'ref',
        ], $releaseArchiveStorageMetadata->toArray());
    }

    public function testReadsMetadataFromDistData(): void
    {
        $metadata = ReleaseArchiveStorageMetadata::fromDistData([
            'type'      => 'zip',
            'url'       => 'https://source.example/archive.zip',
            'reference' => 'ref',
            'storage'   => [
                'driver'           => 's3',
                'bucket'           => 'packio-archives',
                'key'              => 'private/org/org-1/dist/vendor/package/1.0.0_ref.zip',
                'content_type'     => 'application/zip',
                'size'             => 13,
                'sha256'           => 'abc123',
                'etag'             => '"etag"',
                'stored_at'        => '2026-06-10T12:00:00+00:00',
                'source_provider'  => 'github',
                'source_reference' => 'ref',
            ],
        ]);

        self::assertInstanceOf(ReleaseArchiveStorageMetadata::class, $metadata);
        self::assertSame('s3', $metadata->driver);
        self::assertSame('packio-archives', $metadata->bucket);
        self::assertSame('private/org/org-1/dist/vendor/package/1.0.0_ref.zip', $metadata->key);
    }

    public function testReturnsNullWhenDistDataHasNoStorageBlock(): void
    {
        self::assertNull(ReleaseArchiveStorageMetadata::fromDistData([
            'type' => 'zip',
            'url'  => 'https://source.example/archive.zip',
        ]));
    }

    public function testRejectsIncompleteStorageMetadata(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ReleaseArchiveStorageMetadata::fromArray([
            'driver'       => 'local',
            'key'          => 'private/archive.zip',
            'content_type' => 'application/zip',
            'size'         => 13,
            'sha256'       => 'abc123',
        ]);
    }
}
