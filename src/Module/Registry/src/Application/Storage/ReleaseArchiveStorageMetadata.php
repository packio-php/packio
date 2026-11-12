<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Storage;

use Cake\Chronos\Chronos;
use InvalidArgumentException;

use function is_array;
use function trim;

final readonly class ReleaseArchiveStorageMetadata
{
    public function __construct(
        public string $driver,
        public ?string $bucket,
        public string $key,
        public string $contentType,
        public int $size,
        public string $sha256,
        public ?string $etag,
        public Chronos $storedAt,
        public ?string $sourceProvider,
        public ?string $sourceReference,
    ) {
        if ('' === trim($this->driver)) {
            throw new InvalidArgumentException('Archive storage metadata driver is required.');
        }

        if ('' === trim($this->key)) {
            throw new InvalidArgumentException('Archive storage metadata key is required.');
        }

        if ('' === trim($this->contentType)) {
            throw new InvalidArgumentException('Archive storage metadata content type is required.');
        }

        if ($this->size < 0) {
            throw new InvalidArgumentException('Archive storage metadata size must not be negative.');
        }

        if ('' === trim($this->sha256)) {
            throw new InvalidArgumentException('Archive storage metadata sha256 is required.');
        }
    }

    public static function fromStoredArchive(
        string $driver,
        StoredArchive $storedArchive,
        ?string $sourceProvider = null,
        ?string $sourceReference = null,
    ): self {
        return new self(
            driver: $driver,
            bucket: $storedArchive->bucket,
            key: $storedArchive->key,
            contentType: $storedArchive->contentType,
            size: $storedArchive->size,
            sha256: $storedArchive->sha256,
            etag: $storedArchive->etag,
            storedAt: $storedArchive->storedAt,
            sourceProvider: $sourceProvider,
            sourceReference: $sourceReference,
        );
    }

    /**
     * @param array<string, mixed> $distData
     */
    public static function fromDistData(array $distData): ?self
    {
        $storageData = $distData['storage'] ?? null;
        if (! is_array($storageData)) {
            return null;
        }

        return self::fromArray($storageData);
    }

    /**
     * @param array<string, mixed> $storageData
     */
    public static function fromArray(array $storageData): self
    {
        return new self(
            driver: (string) ($storageData['driver'] ?? ''),
            bucket: isset($storageData['bucket']) ? (string) $storageData['bucket'] : null,
            key: (string) ($storageData['key'] ?? ''),
            contentType: (string) ($storageData['content_type'] ?? ''),
            size: (int) ($storageData['size'] ?? -1),
            sha256: (string) ($storageData['sha256'] ?? ''),
            etag: isset($storageData['etag']) ? (string) $storageData['etag'] : null,
            storedAt: self::storedAtFromValue($storageData['stored_at'] ?? null),
            sourceProvider: isset($storageData['source_provider']) ? (string) $storageData['source_provider'] : null,
            sourceReference: isset($storageData['source_reference']) ? (string) $storageData['source_reference'] : null,
        );
    }

    /**
     * @return array{
     *     driver: string,
     *     bucket: null|string,
     *     key: string,
     *     content_type: string,
     *     size: int,
     *     sha256: string,
     *     etag: null|string,
     *     stored_at: string,
     *     source_provider: null|string,
     *     source_reference: null|string
     * }
     */
    public function toArray(): array
    {
        return [
            'driver'           => $this->driver,
            'bucket'           => $this->bucket,
            'key'              => $this->key,
            'content_type'     => $this->contentType,
            'size'             => $this->size,
            'sha256'           => $this->sha256,
            'etag'             => $this->etag,
            'stored_at'        => $this->storedAt->format(Chronos::ATOM),
            'source_provider'  => $this->sourceProvider,
            'source_reference' => $this->sourceReference,
        ];
    }

    private static function storedAtFromValue(mixed $value): Chronos
    {
        $storedAt = trim((string) $value);
        if ('' === $storedAt) {
            throw new InvalidArgumentException('Archive storage metadata stored_at is required.');
        }

        return new Chronos($storedAt);
    }
}
