<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Application\Service;

use Laminas\Diactoros\Stream;
use Packio\Registry\Application\Dto\ReleaseData;
use Packio\Registry\Application\Exception\ReleaseArchiveFetchFailedException;
use Packio\Registry\Application\Exception\ReleaseArchiveStorageUnavailableException;
use Packio\Registry\Application\Port\ReleaseStorageMetadataPort;
use Packio\Registry\Application\Port\SourceQueryPort;
use Packio\Registry\Application\Service\DistArchiveFetcherInterface;
use Packio\Registry\Application\Service\ReleaseArchiveDescriptorInterface;
use Packio\Registry\Application\Service\ReleaseArchiveStorageService;
use Packio\Registry\Application\Storage\ArchiveObjectMetadata;
use Packio\Registry\Application\Storage\ArchiveReadStream;
use Packio\Registry\Application\Storage\DistArchiveStream;
use Packio\Registry\Application\Storage\PackageArchiveKeyGenerator;
use Packio\Registry\Application\Storage\PackageArchiveStorageInterface;
use Packio\Registry\Application\Storage\StoredArchive;
use PackioTest\Support\Base\UnitTestCase;
use PackioTest\Support\Fake\InMemoryPackageArchiveStorage;
use RuntimeException;

use function fopen;
use function fwrite;
use function hash;
use function rewind;
use function strlen;

final class ReleaseArchiveStorageServiceTest extends UnitTestCase
{
    public function testReturnsNullWhenArchiveDescriptorIsMissing(): void
    {
        $inMemoryPackageArchiveStorage       = new InMemoryPackageArchiveStorage();
        $fakeDistArchiveFetcher              = new FakeDistArchiveFetcher();
        $capturingReleaseStorageMetadataPort = new CapturingReleaseStorageMetadataPort();

        self::assertNull($this->service(null, $inMemoryPackageArchiveStorage, $fakeDistArchiveFetcher, $capturingReleaseStorageMetadataPort)->ensureStoredArchive(
            'org-1',
            'vendor/package',
            '1.0.0',
        ));
        self::assertSame(0, $fakeDistArchiveFetcher->fetchCount);
        self::assertSame([], $capturingReleaseStorageMetadataPort->updates);
    }

    public function testReturnsNullWhenExpectedReferenceDoesNotMatchCurrentRelease(): void
    {
        $inMemoryPackageArchiveStorage       = new InMemoryPackageArchiveStorage();
        $fakeDistArchiveFetcher              = new FakeDistArchiveFetcher();
        $capturingReleaseStorageMetadataPort = new CapturingReleaseStorageMetadataPort();

        $archive = $this->service($this->descriptor(), $inMemoryPackageArchiveStorage, $fakeDistArchiveFetcher, $capturingReleaseStorageMetadataPort)->ensureStoredArchive(
            'org-1',
            'vendor/package',
            '1.0.0',
            'stale-ref',
        );

        self::assertNull($archive);
        self::assertSame(0, $fakeDistArchiveFetcher->fetchCount);
        self::assertSame([], $capturingReleaseStorageMetadataPort->updates);
        self::assertFalse($inMemoryPackageArchiveStorage->exists('private/org/org-1/dist/vendor/package/1.0.0_ref.zip'));
    }

    public function testReturnsExistingArchiveWithoutFetchingOrPersistingWhenMetadataMatches(): void
    {
        $inMemoryPackageArchiveStorage = new InMemoryPackageArchiveStorage();
        $key                           = 'private/org/org-1/dist/vendor/package/1.0.0_ref.zip';
        $inMemoryPackageArchiveStorage->put($key, 'archive-bytes', new ArchiveObjectMetadata(
            contentType: 'application/zip',
            contentLength: 13,
            sha256: hash('sha256', 'archive-bytes'),
        ));
        $fakeDistArchiveFetcher              = new FakeDistArchiveFetcher();
        $capturingReleaseStorageMetadataPort = new CapturingReleaseStorageMetadataPort(existingStorageData: [
            'driver'           => 'local',
            'bucket'           => null,
            'key'              => $key,
            'content_type'     => 'application/zip',
            'size'             => 13,
            'sha256'           => hash('sha256', 'archive-bytes'),
            'etag'             => '"' . hash('sha256', 'archive-bytes') . '"',
            'stored_at'        => '2026-06-10T12:00:00+00:00',
            'source_provider'  => 'github',
            'source_reference' => 'ref',
        ]);

        $archive = $this->service($this->descriptor(), $inMemoryPackageArchiveStorage, $fakeDistArchiveFetcher, $capturingReleaseStorageMetadataPort)->ensureStoredArchive(
            'org-1',
            'vendor/package',
            '1.0.0',
        );

        self::assertNotNull($archive);
        self::assertSame($key, $archive->key);
        self::assertSame('repo-ref.zip', $archive->fileName);
        self::assertSame('application/zip', $archive->contentType);
        self::assertSame(13, $archive->size);
        self::assertSame(0, $fakeDistArchiveFetcher->fetchCount);
        self::assertSame([], $capturingReleaseStorageMetadataPort->updates);
    }

    public function testReturnsExistingArchiveWithoutFetchingAndRepairsMissingStorageMetadata(): void
    {
        $inMemoryPackageArchiveStorage = new InMemoryPackageArchiveStorage();
        $key                           = 'private/org/org-1/dist/vendor/package/1.0.0_ref.zip';
        $inMemoryPackageArchiveStorage->put($key, 'archive-bytes', new ArchiveObjectMetadata(
            contentType: 'application/zip',
            contentLength: 13,
            sha256: hash('sha256', 'archive-bytes'),
        ));
        $fakeDistArchiveFetcher              = new FakeDistArchiveFetcher();
        $capturingReleaseStorageMetadataPort = new CapturingReleaseStorageMetadataPort();

        $archive = $this->service($this->descriptor(), $inMemoryPackageArchiveStorage, $fakeDistArchiveFetcher, $capturingReleaseStorageMetadataPort)->ensureStoredArchive(
            'org-1',
            'vendor/package',
            '1.0.0',
        );

        self::assertNotNull($archive);
        self::assertSame($key, $archive->key);
        self::assertSame('repo-ref.zip', $archive->fileName);
        self::assertSame('application/zip', $archive->contentType);
        self::assertSame(13, $archive->size);
        self::assertSame(0, $fakeDistArchiveFetcher->fetchCount);
        self::assertCount(1, $capturingReleaseStorageMetadataPort->updates);
        self::assertSame($key, $capturingReleaseStorageMetadataPort->updates[0]['storage']['key']);
        self::assertSame(13, $capturingReleaseStorageMetadataPort->updates[0]['storage']['size']);
        self::assertSame(hash('sha256', 'archive-bytes'), $capturingReleaseStorageMetadataPort->updates[0]['storage']['sha256']);
    }

    public function testReturnsExistingArchiveWhenStorageMetadataRepairFails(): void
    {
        $inMemoryPackageArchiveStorage = new InMemoryPackageArchiveStorage();
        $key                           = 'private/org/org-1/dist/vendor/package/1.0.0_ref.zip';
        $inMemoryPackageArchiveStorage->put($key, 'archive-bytes', new ArchiveObjectMetadata(
            contentType: 'application/zip',
            contentLength: 13,
            sha256: hash('sha256', 'archive-bytes'),
        ));
        $fakeDistArchiveFetcher              = new FakeDistArchiveFetcher();
        $capturingReleaseStorageMetadataPort = new CapturingReleaseStorageMetadataPort(returnNull: true);

        $archive = $this->service($this->descriptor(), $inMemoryPackageArchiveStorage, $fakeDistArchiveFetcher, $capturingReleaseStorageMetadataPort)->ensureStoredArchive(
            'org-1',
            'vendor/package',
            '1.0.0',
        );

        self::assertNotNull($archive);
        self::assertSame($key, $archive->key);
        self::assertSame(13, $archive->size);
        self::assertSame(0, $fakeDistArchiveFetcher->fetchCount);
    }

    public function testFetchesStoresAndPersistsMissingArchive(): void
    {
        $inMemoryPackageArchiveStorage = new InMemoryPackageArchiveStorage();
        $fakeDistArchiveFetcher        = new FakeDistArchiveFetcher(FakeDistArchiveFetcher::archive(
            content: 'archive-bytes',
            contentType: 'application/zip',
            fileName: 'provider-file.zip',
        ));
        $capturingReleaseStorageMetadataPort = new CapturingReleaseStorageMetadataPort();

        $archive = $this->service($this->descriptor(), $inMemoryPackageArchiveStorage, $fakeDistArchiveFetcher, $capturingReleaseStorageMetadataPort)->ensureStoredArchive(
            'org-1',
            'vendor/package',
            '1.0.0',
        );

        self::assertNotNull($archive);
        self::assertSame('private/org/org-1/dist/vendor/package/1.0.0_ref.zip', $archive->key);
        self::assertSame('repo-ref.zip', $archive->fileName);
        self::assertSame(13, $archive->size);
        self::assertTrue($inMemoryPackageArchiveStorage->exists($archive->key));
        self::assertSame(1, $fakeDistArchiveFetcher->fetchCount);
        self::assertCount(1, $capturingReleaseStorageMetadataPort->updates);
        self::assertSame([
            'organization_id' => 'org-1',
            'package_name'    => 'vendor/package',
            'version'         => '1.0.0',
        ], $capturingReleaseStorageMetadataPort->updates[0]['release']);
        self::assertSame('local', $capturingReleaseStorageMetadataPort->updates[0]['storage']['driver']);
        self::assertNull($capturingReleaseStorageMetadataPort->updates[0]['storage']['bucket']);
        self::assertSame($archive->key, $capturingReleaseStorageMetadataPort->updates[0]['storage']['key']);
        self::assertSame('application/zip', $capturingReleaseStorageMetadataPort->updates[0]['storage']['content_type']);
        self::assertSame(13, $capturingReleaseStorageMetadataPort->updates[0]['storage']['size']);
        self::assertSame(hash('sha256', 'archive-bytes'), $capturingReleaseStorageMetadataPort->updates[0]['storage']['sha256']);
        self::assertSame('github', $capturingReleaseStorageMetadataPort->updates[0]['storage']['source_provider']);
        self::assertSame('ref', $capturingReleaseStorageMetadataPort->updates[0]['storage']['source_reference']);
    }

    public function testDeletesStoredArchiveWhenStorageMetadataCannotBePersisted(): void
    {
        $inMemoryPackageArchiveStorage       = new InMemoryPackageArchiveStorage();
        $fakeDistArchiveFetcher              = new FakeDistArchiveFetcher(FakeDistArchiveFetcher::archive(content: 'archive-bytes'));
        $capturingReleaseStorageMetadataPort = new CapturingReleaseStorageMetadataPort(returnNull: true);

        $this->expectException(ReleaseArchiveStorageUnavailableException::class);
        $this->expectExceptionMessage('Release archive storage metadata could not be persisted: Release archive storage metadata target release was not found.');

        try {
            $this->service($this->descriptor(), $inMemoryPackageArchiveStorage, $fakeDistArchiveFetcher, $capturingReleaseStorageMetadataPort)
                ->ensureStoredArchive('org-1', 'vendor/package', '1.0.0')
            ;
        } finally {
            self::assertFalse($inMemoryPackageArchiveStorage->exists('private/org/org-1/dist/vendor/package/1.0.0_ref.zip'));
            self::assertSame([], $capturingReleaseStorageMetadataPort->updates);
        }
    }

    public function testRejectsFetchedArchiveThatExceedsConfiguredMaximumWhileStreaming(): void
    {
        $inMemoryPackageArchiveStorage       = new InMemoryPackageArchiveStorage();
        $fakeDistArchiveFetcher              = new FakeDistArchiveFetcher(FakeDistArchiveFetcher::archive(content: 'archive-bytes'));
        $capturingReleaseStorageMetadataPort = new CapturingReleaseStorageMetadataPort();

        $this->expectException(ReleaseArchiveFetchFailedException::class);

        try {
            $this->service(
                $this->descriptor(),
                $inMemoryPackageArchiveStorage,
                $fakeDistArchiveFetcher,
                $capturingReleaseStorageMetadataPort,
                maxArchiveBytes: 4,
            )->ensureStoredArchive('org-1', 'vendor/package', '1.0.0');
        } finally {
            self::assertFalse($inMemoryPackageArchiveStorage->exists('private/org/org-1/dist/vendor/package/1.0.0_ref.zip'));
            self::assertSame([], $capturingReleaseStorageMetadataPort->updates);
        }
    }

    public function testDownloadDoesNotFallbackToSourceWhenStorageIsUnavailableByDefault(): void
    {
        $fakeDistArchiveFetcher = new FakeDistArchiveFetcher();

        $this->expectException(ReleaseArchiveStorageUnavailableException::class);

        try {
            $this->service(
                $this->descriptor(),
                new UnavailablePackageArchiveStorage(),
                $fakeDistArchiveFetcher,
                new CapturingReleaseStorageMetadataPort(),
            )->downloadArchive('org-1', 'vendor/package', '1.0.0');
        } finally {
            self::assertSame(0, $fakeDistArchiveFetcher->fetchCount);
        }
    }

    public function testDownloadFallbackToSourceRequiresExplicitOptIn(): void
    {
        $fakeDistArchiveFetcher = new FakeDistArchiveFetcher(FakeDistArchiveFetcher::archive(content: 'archive-bytes'));

        $download = $this->service(
            $this->descriptor(),
            new UnavailablePackageArchiveStorage(),
            $fakeDistArchiveFetcher,
            new CapturingReleaseStorageMetadataPort(),
            fallbackToSourceOnStorageUnavailable: true,
        )->downloadArchive('org-1', 'vendor/package', '1.0.0');

        self::assertNotNull($download);
        self::assertSame('archive-bytes', $download->body->getContents());
        self::assertSame('repo-ref.zip', $download->fileName);
        self::assertSame(13, $download->size);
        self::assertSame(1, $fakeDistArchiveFetcher->fetchCount);
    }

    public function testDownloadReturnsNullWhenExpectedReferenceDoesNotMatchCurrentRelease(): void
    {
        $inMemoryPackageArchiveStorage       = new InMemoryPackageArchiveStorage();
        $fakeDistArchiveFetcher              = new FakeDistArchiveFetcher(FakeDistArchiveFetcher::archive(content: 'archive-bytes'));
        $capturingReleaseStorageMetadataPort = new CapturingReleaseStorageMetadataPort();

        $download = $this->service(
            $this->descriptor(),
            $inMemoryPackageArchiveStorage,
            $fakeDistArchiveFetcher,
            $capturingReleaseStorageMetadataPort,
        )->downloadArchive('org-1', 'vendor/package', '1.0.0', 'stale-ref');

        self::assertNull($download);
        self::assertSame(0, $fakeDistArchiveFetcher->fetchCount);
        self::assertFalse($inMemoryPackageArchiveStorage->exists('private/org/org-1/dist/vendor/package/1.0.0_ref.zip'));
    }

    public function testDownloadOnStorageHitBuildsDescriptorOnceWithoutSourceSecret(): void
    {
        $inMemoryPackageArchiveStorage                   = $this->storageWithArchive();
        $fakeReleaseArchiveDescriptorPort                = new FakeReleaseArchiveDescriptorPort($this->descriptor());
        $fakeDistArchiveFetcher                          = new FakeDistArchiveFetcher();
        $sourceQueryPort                                 = $this->createMock(SourceQueryPort::class);
        $sourceQueryPort->expects(self::never())->method('resolveSourceAuthorizationSecret');

        $download = $this->service(
            null,
            $inMemoryPackageArchiveStorage,
            $fakeDistArchiveFetcher,
            new CapturingReleaseStorageMetadataPort(existingStorageData: $this->matchingStorageData()),
            sourceQueryPort: $sourceQueryPort,
            fakeReleaseArchiveDescriptorPort: $fakeReleaseArchiveDescriptorPort,
        )->downloadArchive('org-1', 'vendor/package', '1.0.0');

        self::assertNotNull($download);
        self::assertSame('archive-bytes', $download->body->getContents());
        self::assertSame(1, $fakeReleaseArchiveDescriptorPort->calls);
        self::assertSame(0, $fakeDistArchiveFetcher->fetchCount);
    }

    public function testUpstreamFetchUsesSourceSecretResolvedOnce(): void
    {
        $fakeReleaseArchiveDescriptorPort         = new FakeReleaseArchiveDescriptorPort($this->descriptor());
        $fakeDistArchiveFetcher                   = new FakeDistArchiveFetcher(FakeDistArchiveFetcher::archive(content: 'archive-bytes'));
        $sourceQueryPort                          = $this->createMock(SourceQueryPort::class);
        $sourceQueryPort->expects(self::once())
            ->method('resolveSourceAuthorizationSecret')
            ->with('source-1')
            ->willReturn('secret-token')
        ;

        $download = $this->service(
            null,
            new InMemoryPackageArchiveStorage(),
            $fakeDistArchiveFetcher,
            new CapturingReleaseStorageMetadataPort(),
            sourceQueryPort: $sourceQueryPort,
            fakeReleaseArchiveDescriptorPort: $fakeReleaseArchiveDescriptorPort,
        )->downloadArchive('org-1', 'vendor/package', '1.0.0');

        self::assertNotNull($download);
        self::assertSame(['secret-token'], $fakeDistArchiveFetcher->tokens);
        self::assertSame(1, $fakeReleaseArchiveDescriptorPort->calls);
    }

    public function testFallbackFetchUsesResolvedSourceSecret(): void
    {
        $fakeReleaseArchiveDescriptorPort         = new FakeReleaseArchiveDescriptorPort($this->descriptor());
        $fakeDistArchiveFetcher                   = new FakeDistArchiveFetcher(FakeDistArchiveFetcher::archive(content: 'archive-bytes'));
        $sourceQueryPort                          = $this->createMock(SourceQueryPort::class);
        $sourceQueryPort->expects(self::once())
            ->method('resolveSourceAuthorizationSecret')
            ->with('source-1')
            ->willReturn('secret-token')
        ;

        $download = $this->service(
            null,
            new UnreadablePackageArchiveStorage($this->storageWithArchive()),
            $fakeDistArchiveFetcher,
            new CapturingReleaseStorageMetadataPort(existingStorageData: $this->matchingStorageData()),
            fallbackToSourceOnStorageUnavailable: true,
            sourceQueryPort: $sourceQueryPort,
            fakeReleaseArchiveDescriptorPort: $fakeReleaseArchiveDescriptorPort,
        )->downloadArchive('org-1', 'vendor/package', '1.0.0');

        self::assertNotNull($download);
        self::assertSame('archive-bytes', $download->body->getContents());
        self::assertSame(['secret-token'], $fakeDistArchiveFetcher->tokens);
        self::assertSame(1, $fakeReleaseArchiveDescriptorPort->calls);
    }

    private function storageWithArchive(): InMemoryPackageArchiveStorage
    {
        $inMemoryPackageArchiveStorage = new InMemoryPackageArchiveStorage();
        $inMemoryPackageArchiveStorage->put('private/org/org-1/dist/vendor/package/1.0.0_ref.zip', 'archive-bytes', new ArchiveObjectMetadata(
            contentType: 'application/zip',
            contentLength: 13,
            sha256: hash('sha256', 'archive-bytes'),
        ));

        return $inMemoryPackageArchiveStorage;
    }

    /**
     * @return array<string, mixed>
     */
    private function matchingStorageData(): array
    {
        return [
            'driver'           => 'local',
            'bucket'           => null,
            'key'              => 'private/org/org-1/dist/vendor/package/1.0.0_ref.zip',
            'content_type'     => 'application/zip',
            'size'             => 13,
            'sha256'           => hash('sha256', 'archive-bytes'),
            'etag'             => '"' . hash('sha256', 'archive-bytes') . '"',
            'stored_at'        => '2026-06-10T12:00:00+00:00',
            'source_provider'  => 'github',
            'source_reference' => 'ref',
        ];
    }

    /**
     * @param null|array{provider: string, owner: string, repository: string, reference: string, source_id: string, file_name: string, base_url: string, provider_config: array<string, mixed>} $descriptor
     */
    private function service(
        ?array $descriptor,
        PackageArchiveStorageInterface $packageArchiveStorage,
        FakeDistArchiveFetcher $fakeDistArchiveFetcher,
        CapturingReleaseStorageMetadataPort $capturingReleaseStorageMetadataPort,
        int $maxArchiveBytes = 52_428_800,
        bool $fallbackToSourceOnStorageUnavailable = false,
        ?SourceQueryPort $sourceQueryPort = null,
        ?FakeReleaseArchiveDescriptorPort $fakeReleaseArchiveDescriptorPort = null,
    ): ReleaseArchiveStorageService {
        return new ReleaseArchiveStorageService(
            $fakeReleaseArchiveDescriptorPort ?? new FakeReleaseArchiveDescriptorPort($descriptor),
            $packageArchiveStorage,
            new PackageArchiveKeyGenerator('private'),
            $fakeDistArchiveFetcher,
            $capturingReleaseStorageMetadataPort,
            $sourceQueryPort ?? $this->createStub(SourceQueryPort::class),
            'local',
            $maxArchiveBytes,
            $fallbackToSourceOnStorageUnavailable,
        );
    }

    /**
     * @return array{provider: string, owner: string, repository: string, reference: string, source_id: string, file_name: string, base_url: string, provider_config: array<string, mixed>}
     */
    private function descriptor(): array
    {
        return [
            'provider'        => 'github',
            'owner'           => 'owner',
            'repository'      => 'repo',
            'reference'       => 'ref',
            'source_id'       => 'source-1',
            'file_name'       => 'repo-ref.zip',
            'base_url'        => '',
            'provider_config' => [],
        ];
    }
}

final class UnavailablePackageArchiveStorage implements PackageArchiveStorageInterface
{
    public function exists(string $key): bool
    {
        throw new RuntimeException('Archive storage is unavailable.');
    }

    public function list(string $prefix): iterable
    {
        return [];
    }

    public function put(string $key, mixed $body, ArchiveObjectMetadata $archiveObjectMetadata): StoredArchive
    {
        throw new RuntimeException('Archive storage is unavailable.');
    }

    public function read(string $key): ArchiveReadStream
    {
        throw new RuntimeException('Archive storage is unavailable.');
    }

    public function delete(string $key): void
    {
        throw new RuntimeException('Archive storage is unavailable.');
    }
}

final readonly class UnreadablePackageArchiveStorage implements PackageArchiveStorageInterface
{
    public function __construct(private PackageArchiveStorageInterface $packageArchiveStorage) {}

    public function exists(string $key): bool
    {
        return $this->packageArchiveStorage->exists($key);
    }

    public function list(string $prefix): iterable
    {
        return $this->packageArchiveStorage->list($prefix);
    }

    public function put(string $key, mixed $body, ArchiveObjectMetadata $archiveObjectMetadata): StoredArchive
    {
        return $this->packageArchiveStorage->put($key, $body, $archiveObjectMetadata);
    }

    public function read(string $key): ArchiveReadStream
    {
        throw new RuntimeException('Archive storage read failed.');
    }

    public function delete(string $key): void
    {
        $this->packageArchiveStorage->delete($key);
    }
}

final class FakeReleaseArchiveDescriptorPort implements ReleaseArchiveDescriptorInterface
{
    public int $calls = 0;

    /**
     * @param null|array{provider: string, owner: string, repository: string, reference: string, source_id: string, file_name: string, base_url: string, provider_config: array<string, mixed>} $descriptor
     */
    public function __construct(private readonly ?array $descriptor) {}

    public function archiveDescriptor(string $packageName, string $version, string $organizationId): ?array
    {
        ++$this->calls;

        return $this->descriptor;
    }
}

final class FakeDistArchiveFetcher implements DistArchiveFetcherInterface
{
    public int $fetchCount = 0;

    /** @var list<null|string> */
    public array $tokens = [];

    public function __construct(private readonly ?DistArchiveStream $distArchiveStream = null) {}

    public function fetchArchive(
        string $provider,
        string $owner,
        string $repository,
        string $reference,
        ?string $token = null,
        string $baseUrl = '',
        array $providerConfig = [],
    ): DistArchiveStream {
        ++$this->fetchCount;
        $this->tokens[] = $token;

        return $this->distArchiveStream ?? self::archive('archive-bytes');
    }

    public static function archive(
        string $content,
        string $contentType = 'application/zip',
        string $fileName = 'package.zip'
    ): DistArchiveStream {
        $resource = fopen('php://temp', 'rb+');
        if (false === $resource) {
            throw new RuntimeException('Failed to open archive stream.');
        }

        fwrite($resource, $content);
        rewind($resource);

        return new DistArchiveStream(
            body: new Stream($resource),
            contentType: $contentType,
            contentLength: strlen($content),
            fileName: $fileName,
        );
    }
}

final class CapturingReleaseStorageMetadataPort implements ReleaseStorageMetadataPort
{
    /** @var list<array{release: array{organization_id: string, package_name: string, version: string}, storage: array<string, mixed>}> */
    public array $updates = [];

    /**
     * @param null|array<string, mixed> $existingStorageData
     */
    public function __construct(private readonly bool $returnNull = false, private readonly ?array $existingStorageData = null) {}

    public function findDistStorageMetadata(string $organizationId, string $packageName, string $version): ?array
    {
        return $this->existingStorageData;
    }

    public function distStorageKeysForPackage(string $organizationId, string $packageName): array
    {
        return [];
    }

    public function updateDistStorageMetadata(
        string $organizationId,
        string $packageName,
        string $version,
        array $storageData
    ): ?ReleaseData {
        if ($this->returnNull) {
            return null;
        }

        $this->updates[] = [
            'release' => [
                'organization_id' => $organizationId,
                'package_name'    => $packageName,
                'version'         => $version,
            ],
            'storage' => $storageData,
        ];

        return new ReleaseData(
            id: $packageName . ':' . $version,
            version: $version,
            reference: (string) ($storageData['source_reference'] ?? ''),
            active: true,
            composerJson: [],
            distMetadata: [],
            source: [],
        );
    }
}
