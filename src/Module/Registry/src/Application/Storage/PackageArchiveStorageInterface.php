<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Storage;

use Psr\Http\Message\StreamInterface;

interface PackageArchiveStorageInterface
{
    public function exists(string $key): bool;

    /** @return iterable<ArchiveObject> */
    public function list(string $prefix): iterable;

    /**
     * @param resource|StreamInterface|string $body
     */
    public function put(string $key, mixed $body, ArchiveObjectMetadata $archiveObjectMetadata): StoredArchive;

    public function read(string $key): ArchiveReadStream;

    public function delete(string $key): void;
}
