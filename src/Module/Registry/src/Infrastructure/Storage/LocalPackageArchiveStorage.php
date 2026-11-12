<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Storage;

use const JSON_THROW_ON_ERROR;

use Cake\Chronos\Chronos;
use FilesystemIterator;
use Laminas\Diactoros\Stream;
use Packio\Registry\Application\Storage\ArchiveObject;
use Packio\Registry\Application\Storage\ArchiveObjectMetadata;
use Packio\Registry\Application\Storage\ArchiveReadStream;
use Packio\Registry\Application\Storage\PackageArchiveStorageInterface;
use Packio\Registry\Application\Storage\StoredArchive;
use Psr\Http\Message\StreamInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function array_diff;
use function dirname;
use function explode;
use function fclose;
use function feof;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function fopen;
use function fread;
use function fwrite;
use function hash_final;
use function hash_init;
use function hash_update;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_link;
use function is_resource;
use function json_decode;
use function json_encode;
use function mkdir;
use function preg_match;
use function rmdir;
use function rtrim;
use function scandir;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function time;
use function unlink;

final readonly class LocalPackageArchiveStorage implements PackageArchiveStorageInterface
{
    public function __construct(private string $rootPath) {}

    public function exists(string $key): bool
    {
        return is_file($this->path($key));
    }

    public function list(string $prefix): iterable
    {
        $root = rtrim($this->rootPath, '/');
        $path = $this->prefixPath($prefix);
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $pathname = $item->getPathname();
            if (str_ends_with((string) $pathname, '.metadata.json')) {
                continue;
            }

            yield new ArchiveObject(
                key: substr((string) $pathname, strlen($root) + 1),
                size: $item->getSize(),
            );
        }
    }

    public function put(string $key, mixed $body, ArchiveObjectMetadata $archiveObjectMetadata): StoredArchive
    {
        $path      = $this->path($key);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Failed to create local archive storage directory.');
        }

        $target = fopen($path, 'wb');
        if (false === $target) {
            throw new RuntimeException('Failed to open local archive object for writing.');
        }

        $hashContext = hash_init('sha256');
        $size        = 0;

        try {
            if ($body instanceof StreamInterface) {
                while (! $body->eof()) {
                    $chunk = $body->read(8192);
                    if ('' === $chunk) {
                        continue;
                    }

                    hash_update($hashContext, $chunk);
                    $size += strlen($chunk);
                    $this->writeChunk($target, $chunk);
                }
            } elseif (is_resource($body)) {
                while (! feof($body)) {
                    $chunk = fread($body, 8192);
                    if (false === $chunk) {
                        throw new RuntimeException('Failed to read archive stream for local storage.');
                    }

                    if ('' === $chunk) {
                        continue;
                    }

                    hash_update($hashContext, $chunk);
                    $size += strlen($chunk);
                    $this->writeChunk($target, $chunk);
                }
            } else {
                $content = (string) $body;
                hash_update($hashContext, $content);
                $size = strlen($content);
                $this->writeChunk($target, $content);
            }
        } finally {
            fclose($target);
        }

        $sha256 = hash_final($hashContext);
        if (null !== $archiveObjectMetadata->sha256 && $archiveObjectMetadata->sha256 !== $sha256) {
            @unlink($path);

            throw new RuntimeException('Local archive object checksum does not match expected sha256.');
        }

        $storedAt      = new Chronos();
        $storedArchive = new StoredArchive(
            key: $key,
            bucket: null,
            size: $size,
            sha256: $sha256,
            etag: '"' . $sha256 . '"',
            contentType: $archiveObjectMetadata->contentType,
            storedAt: $storedAt,
        );

        try {
            $this->writeMetadata($storedArchive, $archiveObjectMetadata);
        } catch (RuntimeException $exception) {
            $this->delete($key);

            throw $exception;
        }

        return $storedArchive;
    }

    public function read(string $key): ArchiveReadStream
    {
        $path = $this->path($key);
        if (! is_file($path)) {
            throw new RuntimeException('Local archive object does not exist.');
        }

        $metadata = $this->readMetadata($key);
        $size     = filesize($path);

        return new ArchiveReadStream(
            body: new Stream($path, 'rb'),
            size: false !== $size ? $size : null,
            contentType: (string) ($metadata['content_type'] ?? 'application/zip'),
            etag: isset($metadata['etag']) ? (string) $metadata['etag'] : null,
            lastModified: isset($metadata['stored_at']) ? new Chronos((string) $metadata['stored_at']) : null,
            sha256: isset($metadata['sha256']) ? (string) $metadata['sha256'] : null,
        );
    }

    public function delete(string $key): void
    {
        $path         = $this->path($key);
        $metadataPath = $this->metadataPath($key);

        $this->deleteFileIfExists($path);
        $this->deleteFileIfExists($metadataPath);

        $this->pruneEmptyDirectories($metadataPath);
        $this->pruneEmptyDirectories($path);
    }

    private function path(string $key): string
    {
        $this->assertSafeKey($key);

        return rtrim($this->rootPath, '/') . '/' . $key;
    }

    private function prefixPath(string $prefix): string
    {
        $prefix = rtrim($prefix, '/');
        $this->assertSafeKey($prefix);

        return rtrim($this->rootPath, '/') . '/' . $prefix;
    }

    private function assertSafeKey(string $key): void
    {
        $segments = explode('/', $key);

        if (
            '' === $key
            || str_starts_with($key, '/')
            || str_contains($key, '\\')
            || 1 === preg_match('/\A[A-Za-z]:/', $key)
            || in_array('..', $segments, true)
            || in_array('', $segments, true)
        ) {
            throw new RuntimeException('Local archive object key must be a relative normalized storage key.');
        }
    }

    /**
     * @param resource $target
     */
    private function writeChunk(mixed $target, string $chunk): void
    {
        $offset = 0;
        $length = strlen($chunk);

        while ($offset < $length) {
            $written = fwrite($target, substr($chunk, $offset));
            if (false === $written || 0 === $written) {
                throw new RuntimeException('Failed to write archive bytes into local storage.');
            }

            $offset += $written;
        }
    }

    private function metadataPath(string $key): string
    {
        return $this->path($key) . '.metadata.json';
    }

    private function deleteFileIfExists(string $path): void
    {
        if (! file_exists($path) && ! is_link($path)) {
            return;
        }

        if (! is_file($path) && ! is_link($path)) {
            return;
        }

        if (! @unlink($path)) {
            throw new RuntimeException('Failed to delete local archive storage object.');
        }
    }

    private function pruneEmptyDirectories(string $path): void
    {
        $root      = rtrim($this->rootPath, '/');
        $directory = dirname($path);

        while ($directory !== $root && str_starts_with($directory, $root . '/')) {
            if (! is_dir($directory)) {
                $directory = dirname($directory);

                continue;
            }

            $items = scandir($directory);
            if (false === $items || [] !== array_diff($items, ['.', '..'])) {
                return;
            }

            if (! @rmdir($directory)) {
                return;
            }

            $directory = dirname($directory);
        }
    }

    private function writeMetadata(StoredArchive $storedArchive, ArchiveObjectMetadata $archiveObjectMetadata): void
    {
        $metadata = json_encode([
            'key'                 => $storedArchive->key,
            'content_type'        => $storedArchive->contentType,
            'size'                => $storedArchive->size,
            'sha256'              => $storedArchive->sha256,
            'etag'                => $storedArchive->etag,
            'stored_at'           => $storedArchive->storedAt->format(Chronos::ATOM),
            'source_provider'     => $archiveObjectMetadata->sourceProvider,
            'source_reference'    => $archiveObjectMetadata->sourceReference,
            'metadata_written_at' => time(),
        ], JSON_THROW_ON_ERROR);

        $written = @file_put_contents($this->metadataPath($storedArchive->key), $metadata);
        if (false === $written || $written !== strlen($metadata)) {
            throw new RuntimeException('Failed to write local archive object metadata.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readMetadata(string $key): array
    {
        $path = $this->metadataPath($key);
        if (! is_file($path)) {
            return [];
        }

        $metadata = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return is_array($metadata) ? $metadata : [];
    }
}
