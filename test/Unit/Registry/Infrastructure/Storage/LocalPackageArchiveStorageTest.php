<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Storage;

use Packio\Registry\Application\Storage\ArchiveObjectMetadata;
use Packio\Registry\Infrastructure\Storage\LocalPackageArchiveStorage;
use PackioTest\Support\Base\UnitTestCase;
use RuntimeException;

use function fclose;
use function fopen;
use function fread;
use function fwrite;
use function hash;
use function is_file;
use function iterator_to_array;
use function mkdir;
use function rewind;
use function sys_get_temp_dir;
use function uniqid;

final class LocalPackageArchiveStorageTest extends UnitTestCase
{
    public function testWritesReadsAndDeletesArchiveObject(): void
    {
        $root                       = sys_get_temp_dir() . '/' . uniqid('packio-archives-', true);
        $localPackageArchiveStorage = new LocalPackageArchiveStorage($root);
        $key                        = 'private/org/org-1/dist/acme/tools/1.0.0_ref.zip';

        $storedArchive = $localPackageArchiveStorage->put($key, 'archive-bytes', new ArchiveObjectMetadata(
            contentType: 'application/zip',
            contentLength: 13,
            sha256: hash('sha256', 'archive-bytes'),
            sourceProvider: 'github',
            sourceReference: 'ref',
        ));

        self::assertTrue($localPackageArchiveStorage->exists($key));
        self::assertSame($key, $storedArchive->key);
        self::assertSame(13, $storedArchive->size);
        self::assertSame(hash('sha256', 'archive-bytes'), $storedArchive->sha256);
        self::assertFileExists($root . '/' . $key . '.metadata.json');

        $read = $localPackageArchiveStorage->read($key);

        self::assertSame('archive-bytes', $read->body->getContents());
        self::assertSame(13, $read->size);
        self::assertSame('application/zip', $read->contentType);
        self::assertSame($storedArchive->etag, $read->etag);

        $localPackageArchiveStorage->delete($key);

        self::assertFalse($localPackageArchiveStorage->exists($key));
        self::assertFalse(is_file($root . '/' . $key . '.metadata.json'));
        self::assertDirectoryDoesNotExist($root . '/private');
    }

    public function testRejectsChecksumMismatch(): void
    {
        $root                       = sys_get_temp_dir() . '/' . uniqid('packio-archives-', true);
        $localPackageArchiveStorage = new LocalPackageArchiveStorage($root);
        $key                        = 'private/org/org-1/dist/acme/tools/1.0.0_ref.zip';

        $this->expectException(RuntimeException::class);

        $localPackageArchiveStorage->put($key, 'archive-bytes', new ArchiveObjectMetadata(sha256: hash('sha256', 'different')));
    }

    public function testRejectsObjectWhenMetadataCannotBeWritten(): void
    {
        $root                       = sys_get_temp_dir() . '/' . uniqid('packio-archives-', true);
        $localPackageArchiveStorage = new LocalPackageArchiveStorage($root);
        $key                        = 'private/org/org-1/dist/acme/tools/1.0.0_ref.zip';

        self::assertTrue(mkdir($root . '/' . $key . '.metadata.json', 0o775, true));

        $this->expectException(RuntimeException::class);

        try {
            $localPackageArchiveStorage->put($key, 'archive-bytes', new ArchiveObjectMetadata());
        } finally {
            self::assertFalse($localPackageArchiveStorage->exists($key));
        }
    }

    public function testStoresResourceBodyFromCurrentPositionWithMatchingMetadata(): void
    {
        $root                       = sys_get_temp_dir() . '/' . uniqid('packio-archives-', true);
        $localPackageArchiveStorage = new LocalPackageArchiveStorage($root);
        $key                        = 'private/org/org-1/dist/acme/tools/1.0.0_ref.zip';
        $body                       = fopen('php://temp', 'rb+');
        self::assertIsResource($body);
        fwrite($body, 'prefixarchive');
        rewind($body);
        fread($body, 6);

        $storedArchive = $localPackageArchiveStorage->put($key, $body, new ArchiveObjectMetadata());
        fclose($body);

        self::assertSame(7, $storedArchive->size);
        self::assertSame(hash('sha256', 'archive'), $storedArchive->sha256);
        self::assertSame('archive', $localPackageArchiveStorage->read($key)->body->getContents());
    }

    public function testListsArchiveObjectsByPrefixWithoutMetadataSidecars(): void
    {
        $root                       = sys_get_temp_dir() . '/' . uniqid('packio-archives-', true);
        $localPackageArchiveStorage = new LocalPackageArchiveStorage($root);
        $key                        = 'private/org/org-1/dist/acme/tools/dev-main_ref.zip';

        $localPackageArchiveStorage->put($key, 'archive-bytes', new ArchiveObjectMetadata());

        $objects = iterator_to_array($localPackageArchiveStorage->list('private/org/org-1/dist/acme/tools/'));

        self::assertCount(1, $objects);
        self::assertSame($key, $objects[0]->key);
        self::assertSame(13, $objects[0]->size);
    }

    public function testRejectsPathTraversalKeys(): void
    {
        $root                       = sys_get_temp_dir() . '/' . uniqid('packio-archives-', true);
        $localPackageArchiveStorage = new LocalPackageArchiveStorage($root);

        $this->expectException(RuntimeException::class);

        $localPackageArchiveStorage->put('../outside.zip', 'archive-bytes', new ArchiveObjectMetadata());
    }
}
