<?php

declare(strict_types=1);

namespace PackioTest\Integration\Registry\Infrastructure\Storage;

use Aws\S3\S3Client;
use Packio\Registry\Application\Storage\ArchiveObjectMetadata;
use Packio\Registry\Infrastructure\Storage\S3PackageArchiveStorage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function fclose;
use function fopen;
use function fwrite;
use function getenv;
use function hash;
use function iterator_to_array;
use function random_bytes;
use function rewind;
use function sprintf;
use function trim;

#[Group('s3')]
final class S3PackageArchiveStorageContractTest extends TestCase
{
    private ?S3PackageArchiveStorage $s3PackageArchiveStorage = null;
    private ?string $key                                      = null;

    protected function setUp(): void
    {
        parent::setUp();

        if ('1' !== $this->env('PACKIO_TEST_S3')) {
            self::markTestSkipped('Set PACKIO_TEST_S3=1 to run real S3 archive storage contract tests.');
        }

        $bucket            = $this->requiredEnv('PACKIO_TEST_S3_BUCKET');
        $region            = $this->env('PACKIO_TEST_S3_REGION', 'fsn1');
        $endpoint          = $this->requiredEnv('PACKIO_TEST_S3_ENDPOINT');
        $accessKey         = $this->requiredEnv('PACKIO_TEST_S3_ACCESS_KEY');
        $secretKey         = $this->requiredEnv('PACKIO_TEST_S3_SECRET_KEY');
        $pathStyleEndpoint = '1' === $this->env('PACKIO_TEST_S3_PATH_STYLE_ENDPOINT', '0');
        $useSsl            = '0' !== $this->env('PACKIO_TEST_S3_USE_SSL', '1');

        $s3Client = new S3Client([
            'version'                 => 'latest',
            'region'                  => $region,
            'endpoint'                => $endpoint,
            'use_path_style_endpoint' => $pathStyleEndpoint,
            'use_ssl'                 => $useSsl,
            'credentials'             => [
                'key'    => $accessKey,
                'secret' => $secretKey,
            ],
        ]);

        $this->s3PackageArchiveStorage = new S3PackageArchiveStorage($s3Client, $bucket, 5 * 1024 * 1024);
        $this->key                     = sprintf(
            '%s/contract/%s.zip',
            trim($this->env('PACKIO_TEST_S3_PREFIX', 'test'), '/'),
            bin2hex(random_bytes(8)),
        );
    }

    protected function tearDown(): void
    {
        try {
            if ($this->s3PackageArchiveStorage instanceof S3PackageArchiveStorage && null !== $this->key) {
                $this->s3PackageArchiveStorage->delete($this->key);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testPutExistsReadAndDelete(): void
    {
        self::assertInstanceOf(S3PackageArchiveStorage::class, $this->s3PackageArchiveStorage);
        self::assertIsString($this->key);

        $content = 's3-contract-archive-bytes';
        $body    = fopen('php://temp', 'rb+');
        self::assertIsResource($body);
        fwrite($body, $content);
        rewind($body);

        try {
            $storedArchive = $this->s3PackageArchiveStorage->put($this->key, $body, new ArchiveObjectMetadata(
                contentType: 'application/zip',
                contentLength: 25,
                sha256: hash('sha256', $content),
                sourceProvider: 'contract',
                sourceReference: 'ref',
            ));
        } finally {
            fclose($body);
        }

        self::assertSame($this->key, $storedArchive->key);
        self::assertSame(25, $storedArchive->size);
        self::assertSame(hash('sha256', $content), $storedArchive->sha256);
        self::assertTrue($this->s3PackageArchiveStorage->exists($this->key));
        $objects = iterator_to_array($this->s3PackageArchiveStorage->list($this->key));
        self::assertCount(1, $objects);
        self::assertSame($this->key, $objects[0]->key);

        $archiveReadStream = $this->s3PackageArchiveStorage->read($this->key);

        try {
            self::assertSame(25, $archiveReadStream->size);
            self::assertSame(hash('sha256', $content), $archiveReadStream->sha256);
            self::assertSame($content, $archiveReadStream->body->getContents());
        } finally {
            $archiveReadStream->body->close();
        }

        $this->s3PackageArchiveStorage->delete($this->key);
        self::assertFalse($this->s3PackageArchiveStorage->exists($this->key));
        $this->key = null;
    }

    private function env(string $name, string $default = ''): string
    {
        $value = getenv($name);

        return false === $value ? $default : trim($value);
    }

    private function requiredEnv(string $name): string
    {
        $value = $this->env($name);
        if ('' === $value) {
            self::markTestSkipped(sprintf('%s is required for S3 contract tests.', $name));
        }

        return $value;
    }
}
