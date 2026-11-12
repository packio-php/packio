<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Application\Query;

use Packio\Registry\Application\Dto\PackageData;
use Packio\Registry\Application\Dto\ReleaseData;
use Packio\Registry\Application\Dto\SourceData;
use Packio\Registry\Application\Port\PackageQueryPort;
use Packio\Registry\Application\Port\ReleaseQueryPort;
use Packio\Registry\Application\Port\SourceQueryPort;
use Packio\Registry\Application\Query\RegistryQueryService;
use Packio\Source\Domain\ValueObject\SourceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;

#[CoversClass(RegistryQueryService::class)]
final class RegistryQueryServiceTest extends TestCase
{
    #[Test]
    public function packageMetadataUsesDistReferenceInArchiveUrl(): void
    {
        $releaseData = new ReleaseData(
            id: 'vendor/package:2.x-dev',
            version: '2.x-dev',
            reference: '2222222222222222222222222222222222222222',
            active: true,
            composerJson: [
                'require' => [
                    'php' => '^8.5',
                ],
            ],
            distMetadata: [
                'type'      => 'zip',
                'reference' => '2222222222222222222222222222222222222222',
            ],
            source: [
                'type'      => 'git',
                'url'       => 'https://github.com/vendor/package.git',
                'reference' => '2222222222222222222222222222222222222222',
            ],
            releasedAt: '2026-06-16T12:00:00+00:00',
        );

        $metadata = $this->service([$releaseData])->packageMetadata('vendor/package', 'org-1');

        self::assertSame(
            '2222222222222222222222222222222222222222',
            $metadata['packages']['vendor/package'][0]['source']['reference'] ?? null,
        );
        self::assertSame(
            '2222222222222222222222222222222222222222',
            $metadata['packages']['vendor/package'][0]['dist']['reference'] ?? null,
        );
        self::assertSame(
            '/dist/vendor/package/2.x-dev/2222222222222222222222222222222222222222.zip',
            $metadata['packages']['vendor/package'][0]['dist']['url'] ?? null,
        );
    }

    #[Test]
    public function packageMetadataUsesReleaseReferenceWhenDistReferenceIsMissing(): void
    {
        $releaseData = new ReleaseData(
            id: 'vendor/package:1.0.0',
            version: '1.0.0',
            reference: 'v1.0.0',
            active: true,
            composerJson: [],
            distMetadata: [
                'type' => 'zip',
            ],
        );

        $metadata = $this->service([$releaseData])->packageMetadata('vendor/package', 'org-1');

        self::assertSame(
            'v1.0.0',
            $metadata['packages']['vendor/package'][0]['dist']['reference'] ?? null,
        );
        self::assertSame(
            '/dist/vendor/package/1.0.0/v1.0.0.zip',
            $metadata['packages']['vendor/package'][0]['dist']['url'] ?? null,
        );
    }

    #[Test]
    public function packageMetadataEncodesBranchVersionsWithSlashesInArchiveUrl(): void
    {
        $releaseData = new ReleaseData(
            id: 'vendor/package:dev-refactor/new-di-cache',
            version: 'dev-refactor/new-di-cache',
            reference: '3333333333333333333333333333333333333333',
            active: true,
            composerJson: [],
            distMetadata: [
                'type'      => 'zip',
                'reference' => '3333333333333333333333333333333333333333',
            ],
        );

        $metadata = $this->service([$releaseData])->packageMetadata('vendor/package', 'org-1');

        self::assertSame(
            '/dist/vendor/package/dev-refactor%2Fnew-di-cache/3333333333333333333333333333333333333333.zip',
            $metadata['packages']['vendor/package'][0]['dist']['url'] ?? null,
        );
    }

    #[Test]
    public function archiveDescriptorCarriesSourceAuthorizationStrategy(): void
    {
        $releaseData = new ReleaseData(
            id: 'vendor/package:dev-main',
            version: 'dev-main',
            reference: '2222222222222222222222222222222222222222',
            active: true,
            composerJson: [],
            distMetadata: [
                'type'      => 'zip',
                'reference' => '2222222222222222222222222222222222222222',
            ],
        );
        $sourceData = new SourceData(
            id: 'source-1',
            provider: 'bitbucket',
            organizationId: 'org-1',
            owner: 'workspace',
            repository: 'package',
            active: true,
            visibility: 'private',
            authType: 'oauth2',
        );

        $descriptor = $this->service([$releaseData], $sourceData, 'bitbucket')
            ->archiveDescriptor('vendor/package', 'dev-main', 'org-1')
        ;

        self::assertSame('bitbucket', $descriptor['provider'] ?? null);
        self::assertSame('source-1', $descriptor['source_id']);
        self::assertArrayNotHasKey('token', $descriptor);
        self::assertSame('oauth2', $descriptor['provider_config']['auth_strategy'] ?? null);
    }

    #[Test]
    public function archiveProvidersMatchSourceProviders(): void
    {
        self::assertEqualsCanonicalizing(
            array_column(SourceProvider::cases(), 'value'),
            RegistryQueryService::ARCHIVE_PROVIDERS,
        );
    }

    #[Test]
    public function packageMetadataKeepsDistForUnknownSourceType(): void
    {
        $releaseData = new ReleaseData(
            id: 'vendor/package:1.0.0',
            version: '1.0.0',
            reference: 'v1.0.0',
            active: true,
            composerJson: [],
            distMetadata: [
                'type' => 'zip',
                'url'  => 'https://example.com/original.zip',
            ],
        );

        $metadata = $this->service([$releaseData], null, 'svn')->packageMetadata('vendor/package', 'org-1');

        self::assertSame(
            'https://example.com/original.zip',
            $metadata['packages']['vendor/package'][0]['dist']['url'] ?? null,
        );
    }

    #[Test]
    public function archiveDescriptorIsNullForUnknownSourceType(): void
    {
        $releaseData = new ReleaseData(
            id: 'vendor/package:dev-main',
            version: 'dev-main',
            reference: '2222222222222222222222222222222222222222',
            active: true,
            composerJson: [],
            distMetadata: [],
        );

        $sourceData = new SourceData(
            id: 'source-1',
            provider: 'github',
            organizationId: 'org-1',
            owner: 'workspace',
            repository: 'package',
            active: true,
            visibility: 'private',
            authType: 'oauth2',
        );

        self::assertNull(
            $this->service([$releaseData], $sourceData, 'svn')->archiveDescriptor('vendor/package', 'dev-main', 'org-1'),
        );
    }

    #[Test]
    public function packageOverviewTakesLatestVersionsInOneCall(): void
    {
        $package = static fn (string $name): PackageData => new PackageData(
            id: $name,
            name: $name,
            type: 'library',
            sourceId: 'source-1',
            organizationId: 'org-1',
            active: true,
            visibility: 'private',
            description: null,
            sourceType: 'github',
            sourceUrl: 'https://github.com/' . $name,
        );
        $packages = new readonly class([$package('vendor/a'), $package('vendor/b')]) implements PackageQueryPort {
            /** @param list<PackageData> $packages */
            public function __construct(private array $packages) {}

            public function allPackages(string $organizationId): array
            {
                return $this->packages;
            }

            public function findPackageInOrganization(string $organizationId, string $name): ?PackageData
            {
                return null;
            }
        };
        $releases = new class implements ReleaseQueryPort {
            public function releasesForPackage(string $organizationId, string $packageName): array
            {
                TestCase::fail('packageOverview must not query releases per package.');
            }

            public function findRelease(string $organizationId, string $packageName, string $version): ?ReleaseData
            {
                return null;
            }

            public function latestActiveVersions(string $organizationId): array
            {
                return [
                    'vendor/a' => '2.0.0',
                ];
            }
        };

        $overview = new RegistryQueryService($packages, $releases, new FakeSourceQueryPort())
            ->packageOverview('org-1')
        ;

        self::assertSame(['2.0.0', null], array_column($overview, 'latest_version'));
        self::assertSame(['vendor/a', 'vendor/b'], array_column($overview, 'name'));
    }

    /**
     * @param list<ReleaseData> $releases
     */
    private function service(array $releases, ?SourceData $sourceData = null, string $sourceType = 'github'): RegistryQueryService
    {
        return new RegistryQueryService(
            new FakePackageQueryPort($sourceType),
            new FakeReleaseQueryPort($releases),
            new FakeSourceQueryPort($sourceData),
        );
    }
}

final readonly class FakePackageQueryPort implements PackageQueryPort
{
    public function __construct(private string $sourceType = 'github') {}

    public function allPackages(string $organizationId): array
    {
        return [$this->package($organizationId)];
    }

    public function findPackageInOrganization(string $organizationId, string $name): ?PackageData
    {
        return 'vendor/package' === $name ? $this->package($organizationId) : null;
    }

    private function package(string $organizationId): PackageData
    {
        return new PackageData(
            id: 'package-1',
            name: 'vendor/package',
            type: 'library',
            sourceId: 'source-1',
            organizationId: $organizationId,
            active: true,
            visibility: 'private',
            description: 'Package',
            sourceType: $this->sourceType,
            sourceUrl: 'https://github.com/vendor/package',
        );
    }
}

final readonly class FakeReleaseQueryPort implements ReleaseQueryPort
{
    /**
     * @param list<ReleaseData> $releases
     */
    public function __construct(private array $releases) {}

    public function releasesForPackage(string $organizationId, string $packageName): array
    {
        return $this->releases;
    }

    public function findRelease(string $organizationId, string $packageName, string $version): ?ReleaseData
    {
        foreach ($this->releases as $release) {
            if ($release->version === $version) {
                return $release;
            }
        }

        return null;
    }

    public function latestActiveVersions(string $organizationId): array
    {
        return [];
    }
}

final readonly class FakeSourceQueryPort implements SourceQueryPort
{
    public function __construct(private ?SourceData $sourceData = null) {}

    public function findSource(string $sourceId): ?SourceData
    {
        return 'source-1' === $sourceId ? $this->sourceData : null;
    }

    public function resolveSourceAuthorizationSecret(string $sourceId): ?string
    {
        return 'source-1' === $sourceId ? 'source-token' : null;
    }
}
