<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Storage;

use Aws\Exception\AwsException;
use Aws\S3\ObjectUploader;
use Aws\S3\S3ClientInterface;
use Cake\Chronos\Chronos;
use Packio\Registry\Application\Storage\ArchiveObject;
use Packio\Registry\Application\Storage\ArchiveObjectMetadata;
use Packio\Registry\Application\Storage\ArchiveReadStream;
use Packio\Registry\Application\Storage\PackageArchiveStorageInterface;
use Packio\Registry\Application\Storage\StoredArchive;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function hash;
use function is_array;
use function is_resource;
use function strlen;

final readonly class S3PackageArchiveStorage implements PackageArchiveStorageInterface
{
    public function __construct(private S3ClientInterface $s3Client, private string $bucket, private int $multipartThresholdBytes) {}

    public function exists(string $key): bool
    {
        try {
            return $this->s3Client->doesObjectExist($this->bucket, $key);
        } catch (AwsException $exception) {
            throw new RuntimeException('Failed to check S3 archive object existence.', $exception->getCode(), previous: $exception);
        }
    }

    public function list(string $prefix): iterable
    {
        $continuationToken = null;

        do {
            try {
                $args = [
                    'Bucket' => $this->bucket,
                    'Prefix' => $prefix,
                ];

                if (null !== $continuationToken) {
                    $args['ContinuationToken'] = $continuationToken;
                }

                $result = $this->s3Client->listObjectsV2($args);
            } catch (AwsException $exception) {
                throw new RuntimeException('Failed to list S3 archive objects.', $exception->getCode(), previous: $exception);
            }

            foreach ($result['Contents'] ?? [] as $object) {
                if (! is_array($object)) {
                    continue;
                }

                if (! isset($object['Key'])) {
                    continue;
                }

                yield new ArchiveObject(
                    key: (string) $object['Key'],
                    size: isset($object['Size']) ? (int) $object['Size'] : null,
                );
            }

            $continuationToken = isset($result['NextContinuationToken'])
                ? (string) $result['NextContinuationToken']
                : null;
        } while (null !== $continuationToken);
    }

    public function put(string $key, mixed $body, ArchiveObjectMetadata $archiveObjectMetadata): StoredArchive
    {
        $body            = $this->normalizeBody($body);
        [$size, $sha256] = $this->verifiedMetadata($body, $archiveObjectMetadata);
        $options         = [
            'params'        => [
                'ContentType' => $archiveObjectMetadata->contentType,
                'Metadata'    => [
                    'source-provider'  => (string) $archiveObjectMetadata->sourceProvider,
                    'source-reference' => (string) $archiveObjectMetadata->sourceReference,
                    'sha256'           => (string) $archiveObjectMetadata->sha256,
                ],
            ],
            'mup_threshold' => $this->multipartThresholdBytes,
        ];

        $options['params']['ContentLength'] = $size;

        try {
            $result = new ObjectUploader(
                $this->s3Client,
                $this->bucket,
                $key,
                $body,
                'private',
                $options,
            )->upload();
        } catch (AwsException $exception) {
            throw new RuntimeException('Failed to write S3 archive object.', $exception->getCode(), previous: $exception);
        }

        return new StoredArchive(
            key: $key,
            bucket: $this->bucket,
            size: $size,
            sha256: $sha256,
            etag: isset($result['ETag']) ? (string) $result['ETag'] : null,
            contentType: $archiveObjectMetadata->contentType,
            storedAt: new Chronos(),
        );
    }

    public function read(string $key): ArchiveReadStream
    {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
        } catch (AwsException $exception) {
            throw new RuntimeException('Failed to read S3 archive object.', $exception->getCode(), previous: $exception);
        }

        $body = $result['Body'] ?? null;
        if (! $body instanceof StreamInterface) {
            throw new RuntimeException('S3 archive object response did not include a readable stream.');
        }

        $metadata = $result['Metadata'] ?? [];

        return new ArchiveReadStream(
            body: $body,
            size: isset($result['ContentLength']) ? (int) $result['ContentLength'] : null,
            contentType: isset($result['ContentType']) ? (string) $result['ContentType'] : 'application/zip',
            etag: isset($result['ETag']) ? (string) $result['ETag'] : null,
            lastModified: isset($result['LastModified'])
                ? Chronos::instance($result['LastModified'])
                : null,
            sha256: is_array($metadata) && isset($metadata['sha256']) ? (string) $metadata['sha256'] : null,
            bucket: $this->bucket,
        );
    }

    public function delete(string $key): void
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
        } catch (AwsException $exception) {
            throw new RuntimeException('Failed to delete S3 archive object.', $exception->getCode(), previous: $exception);
        }
    }

    private function normalizeBody(mixed $body): mixed
    {
        if ($body instanceof StreamInterface || is_resource($body)) {
            return $body;
        }

        return (string) $body;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function verifiedMetadata(mixed $body, ArchiveObjectMetadata $archiveObjectMetadata): array
    {
        if ($body instanceof StreamInterface || is_resource($body)) {
            if (null === $archiveObjectMetadata->contentLength || null === $archiveObjectMetadata->sha256) {
                throw new RuntimeException('S3 archive stream uploads require verified content length and sha256 metadata.');
            }

            return [$archiveObjectMetadata->contentLength, $archiveObjectMetadata->sha256];
        }

        $size = strlen((string) $body);
        if (null !== $archiveObjectMetadata->contentLength && $archiveObjectMetadata->contentLength !== $size) {
            throw new RuntimeException('S3 archive object content length does not match expected metadata.');
        }

        $sha256 = hash('sha256', (string) $body);
        if (null !== $archiveObjectMetadata->sha256 && $archiveObjectMetadata->sha256 !== $sha256) {
            throw new RuntimeException('S3 archive object checksum does not match expected sha256.');
        }

        return [$size, $sha256];
    }
}
