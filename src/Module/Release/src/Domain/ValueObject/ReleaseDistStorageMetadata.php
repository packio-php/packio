<?php

declare(strict_types=1);

namespace Packio\Release\Domain\ValueObject;

use Cake\Chronos\Chronos;
use InvalidArgumentException;

use function trim;

final readonly class ReleaseDistStorageMetadata
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
            throw new InvalidArgumentException('Release dist storage driver is required.');
        }

        if ('' === trim($this->key)) {
            throw new InvalidArgumentException('Release dist storage key is required.');
        }

        if ('' === trim($this->contentType)) {
            throw new InvalidArgumentException('Release dist storage content type is required.');
        }

        if ($this->size < 0) {
            throw new InvalidArgumentException('Release dist storage size must not be negative.');
        }

        if ('' === trim($this->sha256)) {
            throw new InvalidArgumentException('Release dist storage sha256 is required.');
        }
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
            throw new InvalidArgumentException('Release dist storage stored_at is required.');
        }

        return new Chronos($storedAt);
    }
}
