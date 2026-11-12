<?php

declare(strict_types=1);

namespace PackioTest\Support\Fake;

use Cake\Chronos\Chronos;
use Laminas\Diactoros\Stream;
use Packio\Registry\Application\Storage\ArchiveObject;
use Packio\Registry\Application\Storage\ArchiveObjectMetadata;
use Packio\Registry\Application\Storage\ArchiveReadStream;
use Packio\Registry\Application\Storage\PackageArchiveStorageInterface;
use Packio\Registry\Application\Storage\StoredArchive;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function feof;
use function fopen;
use function fread;
use function fwrite;
use function hash;
use function is_resource;
use function rewind;
use function str_starts_with;
use function strlen;

final class InMemoryPackageArchiveStorage implements PackageArchiveStorageInterface
{
    /** @var array<string, array{content: string, stored: StoredArchive}> */
    private array $objects = [];

    public function exists(string $key): bool
    {
        return isset($this->objects[$key]);
    }

    public function list(string $prefix): iterable
    {
        foreach ($this->objects as $key => $object) {
            if (! str_starts_with($key, $prefix)) {
                continue;
            }

            yield new ArchiveObject(
                key: $key,
                size: $object['stored']->size,
            );
        }
    }

    public function put(string $key, mixed $body, ArchiveObjectMetadata $archiveObjectMetadata): StoredArchive
    {
        $content = $this->readBody($body);
        $sha256  = hash('sha256', $content);

        if (null !== $archiveObjectMetadata->sha256 && $archiveObjectMetadata->sha256 !== $sha256) {
            throw new RuntimeException('In-memory archive object checksum does not match expected sha256.');
        }

        $storedArchive = new StoredArchive(
            key: $key,
            bucket: null,
            size: strlen($content),
            sha256: $sha256,
            etag: '"' . $sha256 . '"',
            contentType: $archiveObjectMetadata->contentType,
            storedAt: new Chronos(),
        );

        $this->objects[$key] = [
            'content' => $content,
            'stored'  => $storedArchive,
        ];

        return $storedArchive;
    }

    public function read(string $key): ArchiveReadStream
    {
        if (! isset($this->objects[$key])) {
            throw new RuntimeException('In-memory archive object does not exist.');
        }

        $stream = fopen('php://temp', 'rb+');
        if (false === $stream) {
            throw new RuntimeException('Failed to open temporary in-memory archive stream.');
        }

        fwrite($stream, $this->objects[$key]['content']);
        rewind($stream);

        $stored = $this->objects[$key]['stored'];

        return new ArchiveReadStream(
            body: new Stream($stream),
            size: $stored->size,
            contentType: $stored->contentType,
            etag: $stored->etag,
            lastModified: $stored->storedAt,
            sha256: $stored->sha256,
            bucket: $stored->bucket,
        );
    }

    public function delete(string $key): void
    {
        unset($this->objects[$key]);
    }

    private function readBody(mixed $body): string
    {
        if ($body instanceof StreamInterface) {
            $content = '';
            while (! $body->eof()) {
                $content .= $body->read(8192);
            }

            return $content;
        }

        if (is_resource($body)) {
            $content = '';
            while (! feof($body)) {
                $chunk = fread($body, 8192);
                if (false === $chunk) {
                    throw new RuntimeException('Failed to read archive stream for in-memory storage.');
                }

                $content .= $chunk;
            }

            return $content;
        }

        return (string) $body;
    }
}
