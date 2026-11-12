<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Service;

use Cake\Chronos\Chronos;
use Laminas\Diactoros\Stream;
use Packio\Registry\Application\Dto\ReleaseData;
use Packio\Registry\Application\Exception\ReleaseArchiveFetchFailedException;
use Packio\Registry\Application\Exception\ReleaseArchiveStorageUnavailableException;
use Packio\Registry\Application\Port\ReleaseStorageMetadataPort;
use Packio\Registry\Application\Port\SourceQueryPort;
use Packio\Registry\Application\Storage\ArchiveObjectMetadata;
use Packio\Registry\Application\Storage\ArchiveReadStream;
use Packio\Registry\Application\Storage\DistArchiveStream;
use Packio\Registry\Application\Storage\PackageArchiveKeyGenerator;
use Packio\Registry\Application\Storage\PackageArchiveStorageInterface;
use Packio\Registry\Application\Storage\ReleaseArchiveDownload;
use Packio\Registry\Application\Storage\ReleaseArchiveStorageMetadata;
use Packio\Registry\Application\Storage\StoredArchive;
use Packio\Registry\Application\Storage\StoredReleaseArchive;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

use function fclose;
use function fopen;
use function fwrite;
use function hash_equals;
use function hash_final;
use function hash_init;
use function hash_update;
use function is_resource;
use function rewind;
use function sprintf;
use function strlen;
use function substr;
use function trim;

final readonly class ReleaseArchiveStorageService
{
    public function __construct(
        private ReleaseArchiveDescriptorInterface $releaseArchiveDescriptor,
        private PackageArchiveStorageInterface $packageArchiveStorage,
        private PackageArchiveKeyGenerator $packageArchiveKeyGenerator,
        private DistArchiveFetcherInterface $distArchiveFetcher,
        private ReleaseStorageMetadataPort $releaseStorageMetadataPort,
        private SourceQueryPort $sourceQueryPort,
        private string $storageDriver,
        private int $maxArchiveBytes = 52_428_800,
        private bool $fallbackToSourceOnStorageUnavailable = false,
        private ?LoggerInterface $logger = null,
    ) {
        if ('' === trim($this->storageDriver)) {
            throw new RuntimeException('Archive storage driver is required.');
        }

        if ($this->maxArchiveBytes < 1) {
            throw new RuntimeException('Archive storage max archive size must be positive.');
        }
    }

    public function downloadArchive(
        string $organizationId,
        string $packageName,
        string $version,
        ?string $expectedReference = null,
    ): ?ReleaseArchiveDownload {
        $descriptor = $this->releaseArchiveDescriptor->archiveDescriptor($packageName, $version, $organizationId);
        if (null === $descriptor) {
            return null;
        }

        if (null !== $expectedReference && ! hash_equals($descriptor['reference'], $expectedReference)) {
            return null;
        }

        $key = $this->archiveKey($organizationId, $packageName, $version, $descriptor['reference']);

        try {
            $storedArchive = $this->storeArchive(
                $organizationId,
                $packageName,
                $version,
                $descriptor,
            );
        } catch (ReleaseArchiveStorageUnavailableException $exception) {
            return $this->downloadArchiveFromSourceFallback(
                $organizationId,
                $packageName,
                $version,
                $descriptor,
                $key,
                'storage_unavailable',
                $exception,
            );
        }

        try {
            $archiveReadStream = $this->packageArchiveStorage->read($storedArchive->key);
        } catch (RuntimeException $exception) {
            return $this->downloadArchiveFromSourceFallback(
                $organizationId,
                $packageName,
                $version,
                $descriptor,
                $storedArchive->key,
                'storage_read_failed',
                $exception,
            );
        }

        return new ReleaseArchiveDownload(
            body: $archiveReadStream->body,
            fileName: $storedArchive->fileName,
            contentType: $archiveReadStream->contentType,
            size: $archiveReadStream->size,
        );
    }

    public function ensureStoredArchive(
        string $organizationId,
        string $packageName,
        string $version,
        ?string $expectedReference = null,
    ): ?StoredReleaseArchive {
        $descriptor = $this->releaseArchiveDescriptor->archiveDescriptor($packageName, $version, $organizationId);
        if (null === $descriptor) {
            return null;
        }

        if (null !== $expectedReference && ! hash_equals($descriptor['reference'], $expectedReference)) {
            return null;
        }

        return $this->storeArchive($organizationId, $packageName, $version, $descriptor);
    }

    /**
     * @param array{provider: string, owner: string, repository: string, reference: string, source_id: string, file_name: string, base_url: string, provider_config: array<string, mixed>} $descriptor
     */
    private function storeArchive(
        string $organizationId,
        string $packageName,
        string $version,
        array $descriptor,
    ): StoredReleaseArchive {
        $key = $this->archiveKey($organizationId, $packageName, $version, $descriptor['reference']);

        try {
            $archiveExists = $this->packageArchiveStorage->exists($key);
        } catch (Throwable $exception) {
            throw new ReleaseArchiveStorageUnavailableException('Release archive storage is unavailable.', $exception->getCode(), previous: $exception);
        }

        if ($archiveExists) {
            $currentStorageMetadata = $this->currentStorageMetadata($organizationId, $packageName, $version);
            if (
                $currentStorageMetadata instanceof ReleaseArchiveStorageMetadata
                && $this->storageMetadataMatches($currentStorageMetadata, $key, $descriptor['provider'], $descriptor['reference'])
            ) {
                $this->logger?->debug('Release archive storage hit.', $this->logContext(
                    $organizationId,
                    $packageName,
                    $version,
                    $key,
                    $descriptor['provider'],
                    $descriptor['reference'],
                    $currentStorageMetadata->size,
                ));

                return new StoredReleaseArchive(
                    key: $currentStorageMetadata->key,
                    fileName: $descriptor['file_name'],
                    contentType: $currentStorageMetadata->contentType,
                    size: $currentStorageMetadata->size,
                );
            }

            $storedReleaseArchive = $this->archiveFromExistingObject($key, $descriptor['file_name']);
            $this->repairStorageMetadataBestEffort(
                $organizationId,
                $packageName,
                $version,
                $key,
                $descriptor['provider'],
                $descriptor['reference'],
            );

            return $storedReleaseArchive;
        }

        try {
            $token       = $this->sourceQueryPort->resolveSourceAuthorizationSecret($descriptor['source_id']);
            $distArchive = $this->distArchiveFetcher->fetchArchive(
                $descriptor['provider'],
                $descriptor['owner'],
                $descriptor['repository'],
                $descriptor['reference'],
                $token,
                $descriptor['base_url'],
                $descriptor['provider_config'],
            );
        } catch (Throwable $exception) {
            throw new ReleaseArchiveFetchFailedException('Release archive could not be fetched from upstream.', $exception->getCode(), previous: $exception);
        }

        $body = null;

        try {
            [$body, $contentLength, $sha256] = $this->spoolFetchedArchive($distArchive);

            try {
                $storedArchive = $this->packageArchiveStorage->put(
                    $key,
                    $body,
                    new ArchiveObjectMetadata(
                        contentType: $distArchive->contentType,
                        contentLength: $contentLength,
                        sha256: $sha256,
                        sourceProvider: $descriptor['provider'],
                        sourceReference: $descriptor['reference'],
                    ),
                );
            } catch (Throwable $exception) {
                throw new ReleaseArchiveStorageUnavailableException('Release archive could not be stored.', $exception->getCode(), previous: $exception);
            }
        } finally {
            if (is_resource($body)) {
                fclose($body);
            }

            $distArchive->body->close();
        }

        try {
            $this->persistStorageMetadata(
                $organizationId,
                $packageName,
                $version,
                $storedArchive,
                $descriptor['provider'],
                $descriptor['reference'],
            );
            $this->logger?->info('Release archive stored.', $this->logContext(
                $organizationId,
                $packageName,
                $version,
                $storedArchive->key,
                $descriptor['provider'],
                $descriptor['reference'],
                $storedArchive->size,
            ));
        } catch (Throwable $exception) {
            $this->packageArchiveStorage->delete($storedArchive->key);

            throw new ReleaseArchiveStorageUnavailableException(
                sprintf('Release archive storage metadata could not be persisted: %s', $exception->getMessage()),
                $exception->getCode(),
                previous: $exception,
            );
        }

        return new StoredReleaseArchive(
            key: $storedArchive->key,
            fileName: $descriptor['file_name'],
            contentType: $storedArchive->contentType,
            size: $storedArchive->size,
        );
    }

    /**
     * @param array{provider: string, owner: string, repository: string, reference: string, source_id: string, file_name: string, base_url: string, provider_config: array<string, mixed>} $descriptor
     */
    private function downloadArchiveFromSourceFallback(
        string $organizationId,
        string $packageName,
        string $version,
        array $descriptor,
        string $key,
        string $reason,
        Throwable $throwable,
    ): ReleaseArchiveDownload {
        if (! $this->fallbackToSourceOnStorageUnavailable) {
            if ($throwable instanceof ReleaseArchiveStorageUnavailableException) {
                throw $throwable;
            }

            throw new ReleaseArchiveStorageUnavailableException(
                'Release archive storage is unavailable.',
                $throwable->getCode(),
                previous: $throwable,
            );
        }

        $this->logger?->warning('Release archive storage unavailable; falling back to source archive.', [
            ...$this->logContext(
                $organizationId,
                $packageName,
                $version,
                $key,
                $descriptor['provider'],
                $descriptor['reference'],
            ),
            'fallback'        => true,
            'fallback_reason' => $reason,
            'exception'       => $throwable::class,
            'error'           => $throwable->getMessage(),
        ]);

        try {
            $token       = $this->sourceQueryPort->resolveSourceAuthorizationSecret($descriptor['source_id']);
            $distArchive = $this->distArchiveFetcher->fetchArchive(
                $descriptor['provider'],
                $descriptor['owner'],
                $descriptor['repository'],
                $descriptor['reference'],
                $token,
                $descriptor['base_url'],
                $descriptor['provider_config'],
            );
        } catch (Throwable $exception) {
            throw new ReleaseArchiveFetchFailedException('Release archive could not be fetched from upstream.', $exception->getCode(), previous: $exception);
        }

        try {
            [$body, $contentLength, $sha256] = $this->spoolFetchedArchive($distArchive);
        } finally {
            $distArchive->body->close();
        }

        try {
            $this->assertFallbackArchiveMatchesStorageMetadata(
                $organizationId,
                $packageName,
                $version,
                $key,
                $descriptor['provider'],
                $descriptor['reference'],
                $contentLength,
                $sha256,
            );
        } catch (Throwable $exception) {
            if (is_resource($body)) {
                fclose($body);
            }

            throw $exception;
        }

        return new ReleaseArchiveDownload(
            body: new Stream($body),
            fileName: $descriptor['file_name'],
            contentType: $distArchive->contentType,
            size: $contentLength,
        );
    }

    private function archiveKey(string $organizationId, string $packageName, string $version, string $reference): string
    {
        return $this->packageArchiveKeyGenerator->key(
            $organizationId,
            $packageName,
            $version,
            $reference,
            'zip',
        );
    }

    /**
     * @return array{0: resource, 1: int, 2: string}
     */
    private function spoolFetchedArchive(DistArchiveStream $distArchiveStream): array
    {
        $body = fopen('php://temp', 'rb+');
        if (false === $body) {
            throw new ReleaseArchiveFetchFailedException('Temporary archive stream could not be opened.');
        }

        $hashContext   = hash_init('sha256');
        $contentLength = 0;

        try {
            while (! $distArchiveStream->body->eof()) {
                $chunk = $distArchiveStream->body->read(8192);
                if ('' === $chunk) {
                    continue;
                }

                hash_update($hashContext, $chunk);
                $contentLength += strlen($chunk);
                if ($contentLength > $this->maxArchiveBytes) {
                    throw new ReleaseArchiveFetchFailedException('Fetched release archive exceeds the configured maximum archive size.');
                }

                $this->writeSpoolChunk($body, $chunk);
            }

            if (null !== $distArchiveStream->contentLength && $distArchiveStream->contentLength !== $contentLength) {
                throw new ReleaseArchiveFetchFailedException('Fetched release archive content length does not match upstream metadata.');
            }

            rewind($body);

            return [$body, $contentLength, hash_final($hashContext)];
        } catch (Throwable $exception) {
            fclose($body);

            if ($exception instanceof ReleaseArchiveFetchFailedException) {
                throw $exception;
            }

            throw new ReleaseArchiveFetchFailedException('Fetched release archive stream could not be read.', $exception->getCode(), previous: $exception);
        }
    }

    /**
     * @param resource $body
     */
    private function writeSpoolChunk(mixed $body, string $chunk): void
    {
        $offset = 0;
        $length = strlen($chunk);

        while ($offset < $length) {
            $written = fwrite($body, substr($chunk, $offset));
            if (false === $written || 0 === $written) {
                throw new RuntimeException('Fetched release archive stream could not be spooled.');
            }

            $offset += $written;
        }
    }

    private function assertFallbackArchiveMatchesStorageMetadata(
        string $organizationId,
        string $packageName,
        string $version,
        string $key,
        string $sourceProvider,
        string $sourceReference,
        int $contentLength,
        string $sha256,
    ): void {
        $currentStorageMetadata = $this->currentStorageMetadata($organizationId, $packageName, $version);
        if (
            ! $currentStorageMetadata instanceof ReleaseArchiveStorageMetadata
            || ! $this->storageMetadataMatches($currentStorageMetadata, $key, $sourceProvider, $sourceReference)
        ) {
            return;
        }

        if ($currentStorageMetadata->size === $contentLength && $currentStorageMetadata->sha256 === $sha256) {
            return;
        }

        throw new ReleaseArchiveFetchFailedException('Fallback release archive does not match stored archive metadata.');
    }

    private function archiveFromExistingObject(string $key, string $fileName): StoredReleaseArchive
    {
        try {
            $archiveReadStream = $this->packageArchiveStorage->read($key);
        } catch (Throwable $exception) {
            throw new ReleaseArchiveStorageUnavailableException('Stored release archive could not be read.', $exception->getCode(), previous: $exception);
        }

        try {
            return new StoredReleaseArchive(
                key: $key,
                fileName: $fileName,
                contentType: $archiveReadStream->contentType,
                size: $archiveReadStream->size,
            );
        } finally {
            $archiveReadStream->body->close();
        }
    }

    private function currentStorageMetadata(string $organizationId, string $packageName, string $version): ?ReleaseArchiveStorageMetadata
    {
        try {
            $storageData = $this->releaseStorageMetadataPort->findDistStorageMetadata($organizationId, $packageName, $version);
        } catch (Throwable) {
            return null;
        }

        if (null === $storageData) {
            return null;
        }

        try {
            return ReleaseArchiveStorageMetadata::fromArray($storageData);
        } catch (Throwable) {
            return null;
        }
    }

    private function storageMetadataMatches(
        ReleaseArchiveStorageMetadata $releaseArchiveStorageMetadata,
        string $key,
        string $sourceProvider,
        string $sourceReference,
    ): bool {
        return $releaseArchiveStorageMetadata->driver === $this->storageDriver
            && $releaseArchiveStorageMetadata->key === $key
            && $releaseArchiveStorageMetadata->sourceProvider === $sourceProvider
            && $releaseArchiveStorageMetadata->sourceReference === $sourceReference;
    }

    private function repairStorageMetadataBestEffort(
        string $organizationId,
        string $packageName,
        string $version,
        string $key,
        string $sourceProvider,
        string $sourceReference,
    ): void {
        try {
            $archiveReadStream = $this->packageArchiveStorage->read($key);

            try {
                $storedArchive = $this->storedArchiveFromReadStream($key, $archiveReadStream);
            } finally {
                $archiveReadStream->body->close();
            }

            $this->persistStorageMetadata(
                $organizationId,
                $packageName,
                $version,
                $storedArchive,
                $sourceProvider,
                $sourceReference,
            );
            $this->logger?->info('Release archive storage metadata repaired.', $this->logContext(
                $organizationId,
                $packageName,
                $version,
                $key,
                $sourceProvider,
                $sourceReference,
                $storedArchive->size,
            ));
        } catch (Throwable $exception) {
            $this->logger?->warning('Release archive storage metadata repair failed.', [
                ...$this->logContext(
                    $organizationId,
                    $packageName,
                    $version,
                    $key,
                    $sourceProvider,
                    $sourceReference,
                ),
                'exception' => $exception::class,
            ]);

            return;
        }
    }

    private function storedArchiveFromReadStream(string $key, ArchiveReadStream $archiveReadStream): StoredArchive
    {
        if (null === $archiveReadStream->size) {
            throw new RuntimeException('Stored release archive size is required to persist metadata.');
        }

        if (null === $archiveReadStream->sha256 || '' === trim($archiveReadStream->sha256)) {
            throw new RuntimeException('Stored release archive sha256 is required to persist metadata.');
        }

        if (! $archiveReadStream->lastModified instanceof Chronos) {
            throw new RuntimeException('Stored release archive timestamp is required to persist metadata.');
        }

        return new StoredArchive(
            key: $key,
            bucket: $archiveReadStream->bucket,
            size: $archiveReadStream->size,
            sha256: $archiveReadStream->sha256,
            etag: $archiveReadStream->etag,
            contentType: $archiveReadStream->contentType,
            storedAt: $archiveReadStream->lastModified,
        );
    }

    private function persistStorageMetadata(
        string $organizationId,
        string $packageName,
        string $version,
        StoredArchive $storedArchive,
        string $sourceProvider,
        string $sourceReference,
    ): void {
        $release = $this->releaseStorageMetadataPort->updateDistStorageMetadata(
            $organizationId,
            $packageName,
            $version,
            ReleaseArchiveStorageMetadata::fromStoredArchive(
                $this->storageDriver,
                $storedArchive,
                $sourceProvider,
                $sourceReference,
            )->toArray(),
        );

        if (! $release instanceof ReleaseData) {
            throw new RuntimeException('Release archive storage metadata target release was not found.');
        }
    }

    /**
     * @return array<string, null|int|string>
     */
    private function logContext(
        string $organizationId,
        string $packageName,
        string $version,
        string $key,
        string $sourceProvider,
        string $sourceReference,
        ?int $size = null,
    ): array {
        return [
            'organization_id'  => $organizationId,
            'package_name'     => $packageName,
            'version'          => $version,
            'storage_driver'   => $this->storageDriver,
            'storage_key'      => $key,
            'source_provider'  => $sourceProvider,
            'source_reference' => $sourceReference,
            'size'             => $size,
        ];
    }
}
