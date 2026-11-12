<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Storage;

use Aws\S3\S3ClientInterface;
use Packio\Registry\Application\Storage\ArchiveObjectMetadata;
use Packio\Registry\Infrastructure\Storage\S3PackageArchiveStorage;
use PackioTest\Support\Base\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use RuntimeException;

use function fclose;
use function fopen;
use function fwrite;
use function rewind;

#[AllowMockObjectsWithoutExpectations]
final class S3PackageArchiveStorageTest extends UnitTestCase
{
    public function testRejectsStreamUploadWithoutVerifiedMetadata(): void
    {
        $body = fopen('php://temp', 'rb+');
        self::assertIsResource($body);
        fwrite($body, 'archive-bytes');
        rewind($body);

        $s3PackageArchiveStorage = new S3PackageArchiveStorage(
            $this->createMock(S3ClientInterface::class),
            'packio-archives',
            16_777_216,
        );

        try {
            $this->expectException(RuntimeException::class);

            $s3PackageArchiveStorage->put('private/org/org-1/dist/acme/tools/1.0.0_ref.zip', $body, new ArchiveObjectMetadata());
        } finally {
            fclose($body);
        }
    }
}
