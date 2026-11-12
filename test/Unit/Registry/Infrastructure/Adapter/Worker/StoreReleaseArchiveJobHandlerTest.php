<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Adapter\Worker;

use Laminas\Diactoros\Stream;
use Packio\Registry\Application\Dto\ReleaseData;
use Packio\Registry\Application\Port\ReleaseStorageMetadataPort;
use Packio\Registry\Application\Port\SourceQueryPort;
use Packio\Registry\Application\Service\DistArchiveFetcherInterface;
use Packio\Registry\Application\Service\ReleaseArchiveDescriptorInterface;
use Packio\Registry\Application\Service\ReleaseArchiveStorageService;
use Packio\Registry\Application\Storage\DistArchiveStream;
use Packio\Registry\Application\Storage\PackageArchiveKeyGenerator;
use Packio\Registry\Infrastructure\Adapter\Worker\StoreReleaseArchiveJobHandler;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Application\JobLogger;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use PackioTest\Support\Fake\InMemoryPackageArchiveStorage;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function fopen;
use function fwrite;
use function rewind;

#[CoversClass(StoreReleaseArchiveJobHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class StoreReleaseArchiveJobHandlerTest extends TestCase
{
    public function testStoresReleaseArchive(): void
    {
        $jobLogger = $this->createMock(JobLoggerInterface::class);
        $jobLogger->expects(self::exactly(2))->method('info');

        $jobResult = $this->handler($this->descriptor())->handle([
            'organization_id' => 'org-1',
            'package_name'    => 'vendor/package',
            'version'         => '1.0.0',
        ], $jobLogger);

        self::assertTrue($jobResult->isSuccess());
        self::assertSame('vendor/package', $jobResult->getData()['package_name'] ?? null);
        self::assertSame('1.0.0', $jobResult->getData()['version'] ?? null);
        self::assertSame('private/org/org-1/dist/vendor/package/1.0.0_ref.zip', $jobResult->getData()['key'] ?? null);
        self::assertSame(13, $jobResult->getData()['size'] ?? null);
    }

    public function testInvalidPayloadFailsWithoutRetry(): void
    {
        $jobResult = $this->handler($this->descriptor())->handle([
            'organization_id' => 'org-1',
            'package_name'    => 'vendor/package',
        ], $this->createMock(JobLoggerInterface::class));

        self::assertFalse($jobResult->isSuccess());
        self::assertFalse($jobResult->shouldRetry());
        self::assertSame('Version is required.', $jobResult->getErrorMessage());
    }

    public function testUnavailableArchiveIsSkippedWithoutRetry(): void
    {
        $jobLogger = new JobLogger('job-1', $this->createStub(JobLogRepositoryInterface::class));

        $jobResult = $this->handler(null)->handle([
            'organization_id' => 'org-1',
            'package_name'    => 'vendor/package',
            'version'         => '1.0.0',
            'reference'       => 'ref-1',
        ], $jobLogger);

        self::assertTrue($jobResult->isSuccess());
        self::assertSame([
            'outcome'      => 'skipped',
            'reason'       => 'release_not_available',
            'package_name' => 'vendor/package',
            'version'      => '1.0.0',
            'reference'    => 'ref-1',
        ], $jobResult->getData());
        self::assertSame([
            'job_id'  => 'job-1',
            'level'   => 'info',
            'message' => 'Release archive storage fill skipped because the release is no longer available',
            'context' => [
                'organization_id' => 'org-1',
                'package_name'    => 'vendor/package',
                'version'         => '1.0.0',
                'reference'       => 'ref-1',
                'outcome'         => 'skipped',
            ],
        ], $jobLogger->getLogs()[1] ?? null);
    }

    /**
     * @param null|array{provider: string, owner: string, repository: string, reference: string, source_id: string, file_name: string, base_url: string, provider_config: array<string, mixed>} $descriptor
     */
    private function handler(?array $descriptor): StoreReleaseArchiveJobHandler
    {
        return new StoreReleaseArchiveJobHandler(new ReleaseArchiveStorageService(
            new FakeReleaseArchiveDescriptorPort($descriptor),
            new InMemoryPackageArchiveStorage(),
            new PackageArchiveKeyGenerator('private'),
            new FakeDistArchiveFetcher(),
            new CapturingReleaseStorageMetadataPort(),
            $this->createStub(SourceQueryPort::class),
            'local',
        ));
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

final readonly class FakeReleaseArchiveDescriptorPort implements ReleaseArchiveDescriptorInterface
{
    /**
     * @param null|array{provider: string, owner: string, repository: string, reference: string, source_id: string, file_name: string, base_url: string, provider_config: array<string, mixed>} $descriptor
     */
    public function __construct(private ?array $descriptor) {}

    public function archiveDescriptor(string $packageName, string $version, string $organizationId): ?array
    {
        return $this->descriptor;
    }
}

final class FakeDistArchiveFetcher implements DistArchiveFetcherInterface
{
    public function fetchArchive(
        string $provider,
        string $owner,
        string $repository,
        string $reference,
        ?string $token = null,
        string $baseUrl = '',
        array $providerConfig = [],
    ): DistArchiveStream {
        $resource = fopen('php://temp', 'rb+');
        if (false === $resource) {
            throw new RuntimeException('Failed to open archive stream.');
        }

        fwrite($resource, 'archive-bytes');
        rewind($resource);

        return new DistArchiveStream(new Stream($resource), contentLength: 13);
    }
}

final class CapturingReleaseStorageMetadataPort implements ReleaseStorageMetadataPort
{
    public function findDistStorageMetadata(string $organizationId, string $packageName, string $version): ?array
    {
        return null;
    }

    public function distStorageKeysForPackage(string $organizationId, string $packageName): array
    {
        return [];
    }

    public function updateDistStorageMetadata(string $organizationId, string $packageName, string $version, array $storageData): ReleaseData
    {
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
