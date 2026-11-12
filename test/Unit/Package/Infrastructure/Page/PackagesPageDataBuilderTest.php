<?php

declare(strict_types=1);

namespace PackioTest\Unit\Package\Infrastructure\Page;

use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Package\Infrastructure\Page\PackageIndexFilterCriteria;
use Packio\Package\Infrastructure\Page\PackagesPageDataBuilder;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(PackagesPageDataBuilder::class)]
final class PackagesPageDataBuilderTest extends TestCase
{
    private const string ORG_ID   = 'org-01';
    private const string ORG_SLUG = 'acme';
    private const string ORG_NAME = 'Acme Corp';

    /** @var MockObject&PackageApiFacadeInterface */
    private PackageApiFacadeInterface $packageApiFacade;

    /** @var MockObject&ReleaseApiFacadeInterface */
    private ReleaseApiFacadeInterface $releaseApiFacade;

    /** @var MockObject&SourceApiFacadeInterface */
    private SourceApiFacadeInterface $sourceApiFacade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packageApiFacade = $this->createMock(PackageApiFacadeInterface::class);
        $this->releaseApiFacade = $this->createMock(ReleaseApiFacadeInterface::class);
        $this->sourceApiFacade  = $this->createMock(SourceApiFacadeInterface::class);
    }

    public function testBuildReturnsExpectedStructureForMember(): void
    {
        $organizationDto = $this->createOrganization();
        $this->expectFacadeCalls($this->samplePackages(), $this->sampleLatestVersions(), $this->sampleSources());

        $result = $this->createBuilder()->build($organizationDto);

        // Organization summary
        self::assertSame([
            'id'               => self::ORG_ID,
            'slug'             => self::ORG_SLUG,
            'name'             => self::ORG_NAME,
            'anonymous_access' => true,
        ], $result['organization_summary']);

        // Member sees all packages (public + private, active + inactive)
        self::assertCount(4, $result['sections']);

        self::assertSame([
            'name'           => 'acme/public-one',
            'description'    => 'Public package one',
            'latest_version' => '1.0.0',
            'visibility'     => 'public',
            'active'         => true,
            'source_url'     => 'https://github.com/acme/public-one',
            'download_count' => 42,
            'source'         => [
                'id'                      => 'source-01',
                'name'                    => 'owner/public-repo',
                'url'                     => 'https://github.com/acme/public-one',
                'type'                    => 'github',
                'visibility'              => 'public',
                'active'                  => true,
                'archived'                => false,
                'status'                  => 'active',
                'last_sync_status'        => 'pending',
                'last_successful_sync_at' => '',
                'last_failed_sync_at'     => '',
            ],
        ], $result['sections'][0]);

        $section = $result['sections'][1];
        self::assertSame('acme/private-one', $section['name']);
        self::assertSame('0.5.0', $section['latest_version']);
        self::assertSame(7, $section['download_count']);
        self::assertNull($section['source']);

        $section = $result['sections'][2];
        self::assertSame('acme/inactive-public', $section['name']);
        self::assertNull($section['latest_version']);
        self::assertNull($section['source']);

        $section = $result['sections'][3];
        self::assertSame('acme/inactive-private', $section['name']);
        self::assertSame(0, $section['download_count']);

        self::assertSame([
            'total'   => 4,
            'listed'  => 2,
            'public'  => 2,
            'private' => 2,
        ], $result['package_stats']);
    }

    public function testBuildIncludesPrivateAndInactivePackagesBecauseRouteIsMemberOnly(): void
    {
        $organizationDto = $this->createOrganization();
        $this->expectFacadeCalls($this->samplePackages(), $this->sampleLatestVersions(), $this->sampleSources());

        $result = $this->createBuilder()->build($organizationDto);

        self::assertCount(4, $result['sections']);
        self::assertSame('acme/public-one', $result['sections'][0]['name']);
        self::assertSame('acme/private-one', $result['sections'][1]['name']);
        self::assertSame('acme/inactive-public', $result['sections'][2]['name']);
        self::assertSame('acme/inactive-private', $result['sections'][3]['name']);
    }

    public function testBuildPaginatesSectionsAndKeepsTotalStats(): void
    {
        $organizationDto = $this->createOrganization();
        $packages        = [];

        for ($index = 1; $index <= 25; ++$index) {
            $packages[] = $this->createPackage(
                name: 'acme/package-' . $index,
                visibility: 0 === $index % 2 ? 'private' : 'public',
                active: $index <= 23,
                sourceUrl: 'https://github.com/acme/package-' . $index,
                description: 'Package ' . $index,
            );
        }

        $this->expectFacadeCalls($packages, [], []);

        $result = $this->createBuilder()->build(
            $organizationDto,
            new PaginationCriteria(page: 2, perPage: 18),
        );

        self::assertCount(7, $result['sections']);
        self::assertSame('acme/package-19', $result['sections'][0]['name']);
        self::assertSame(25, $result['package_stats']['total']);
        self::assertSame(23, $result['package_stats']['listed']);
        self::assertSame(13, $result['package_stats']['public']);
        self::assertSame(12, $result['package_stats']['private']);
        self::assertSame(25, $result['pagination']['total']);
        self::assertSame(2, $result['pagination']['current_page']);
        self::assertSame(2, $result['pagination']['last_page']);
        self::assertSame(19, $result['pagination']['from']);
        self::assertSame(25, $result['pagination']['to']);
        self::assertArrayNotHasKey('items', $result['pagination']);
    }

    public function testBuildReturnsEmptySectionsForOrganizationWithoutPackages(): void
    {
        $organizationDto = $this->createOrganization();
        $this->expectFacadeCalls([], [], []);

        $result = $this->createBuilder()->build($organizationDto);

        self::assertSame([], $result['sections']);
        self::assertSame([
            'total'   => 0,
            'listed'  => 0,
            'public'  => 0,
            'private' => 0,
        ], $result['package_stats']);
        self::assertSame(0, $result['pagination']['total']);
        self::assertSame([], $result['package_filter_options']['source_sync_statuses']);
    }

    public function testBuildUsesLatestActiveVersionPerPackage(): void
    {
        $organizationDto = $this->createOrganization();
        $this->expectFacadeCalls(
            [
                $this->createPackage('acme/one', 'public', true, 'https://github.com/acme/one'),
                $this->createPackage('acme/two', 'private', true, 'https://github.com/acme/two'),
            ],
            [
                'acme/one' => '2.0.0',
            ],
            [],
        );

        $result = $this->createBuilder()->build($organizationDto);

        self::assertSame('acme/one', $result['sections'][0]['name']);
        self::assertSame('2.0.0', $result['sections'][0]['latest_version']);
        self::assertSame('acme/two', $result['sections'][1]['name']);
        self::assertNull($result['sections'][1]['latest_version']);
    }

    public function testSectionsDoNotExposeAccessHintOrRegistryEndpoint(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $this->expectFacadeCalls($this->samplePackages(), $this->sampleLatestVersions(), $this->sampleSources());

        $result = $this->createBuilder()->build($organizationDto);

        foreach ($result['sections'] as $section) {
            self::assertArrayNotHasKey('access_hint', $section);
            self::assertArrayNotHasKey('registry_endpoint', $section);
        }
    }

    public function testSourceSummaryUsesOwnerRepositoryFallback(): void
    {
        $organizationDto = $this->createOrganization();
        $this->expectFacadeCalls(
            [
                $this->createPackage(
                    name: 'acme/with-source',
                    visibility: 'public',
                    active: true,
                    sourceUrl: 'https://github.com/acme/some-repo',
                    sourceId: 'source-01',
                    downloadCount: 10,
                ),
            ],
            [
                'acme/with-source' => '0.1.0',
            ],
            [
                new SourceDto(
                    id: 'source-01',
                    provider: 'github',
                    type: 'github',
                    displayName: '',
                    canonicalUrl: 'https://github.com/acme/some-repo',
                    url: 'https://github.com/acme/some-repo',
                    owner: 'acme',
                    repository: 'some-repo',
                    defaultRef: 'main',
                    organizationId: self::ORG_ID,
                ),
            ],
        );

        $result = $this->createBuilder()->build($organizationDto);

        $source = $result['sections'][0]['source'];
        self::assertNotNull($source);
        self::assertSame('acme/some-repo', $source['name']);
    }

    public function testBuildFiltersPackagesBeforePaginationAndKeepsGlobalStats(): void
    {
        $organizationDto = $this->createOrganization();
        $this->expectFacadeCalls(
            [
                $this->createPackage('acme/app', 'public', true, 'https://github.com/acme/app', 'source-app', 11, 'Application package'),
                $this->createPackage('acme/library', 'private', true, 'https://gitlab.com/acme/library', 'source-library', 3, 'Library package'),
                $this->createPackage('acme/manual-hidden', 'public', false, '', description: 'Manual hidden package'),
            ],
            [
                'acme/app'     => '1.0.0',
                'acme/library' => '0.2.0',
            ],
            [
                new SourceDto(
                    id: 'source-app',
                    provider: 'github',
                    type: 'github',
                    displayName: 'App Source',
                    canonicalUrl: 'https://github.com/acme/app',
                    url: 'https://github.com/acme/app',
                    owner: 'acme',
                    repository: 'app',
                    defaultRef: 'main',
                    organizationId: self::ORG_ID,
                    lastSyncStatus: 'success',
                ),
                new SourceDto(
                    id: 'source-library',
                    provider: 'gitlab',
                    type: 'gitlab',
                    displayName: 'Library Source',
                    canonicalUrl: 'https://gitlab.com/acme/library',
                    url: 'https://gitlab.com/acme/library',
                    owner: 'acme',
                    repository: 'library',
                    defaultRef: 'main',
                    organizationId: self::ORG_ID,
                    lastSyncStatus: 'failed',
                ),
            ],
        );

        $result = $this->createBuilder()->build(
            $organizationDto,
            new PaginationCriteria(page: 1, perPage: 18),
            PackageIndexFilterCriteria::fromArray([
                'search'             => 'app',
                'visibility'         => ['public'],
                'listed'             => 'listed',
                'source'             => 'managed',
                'source_sync_status' => ['success'],
            ]),
        );

        self::assertCount(1, $result['sections']);
        self::assertSame('acme/app', $result['sections'][0]['name']);
        self::assertSame(3, $result['package_stats']['total']);
        self::assertSame(2, $result['package_stats']['listed']);
        self::assertSame(2, $result['package_stats']['public']);
        self::assertSame(1, $result['package_stats']['private']);
        self::assertSame(1, $result['pagination']['total']);
        self::assertSame('listed', $result['filters']['listed']);
        self::assertSame('managed', $result['filters']['source']);
        self::assertSame(['success'], $result['filters']['source_sync_status']);
        self::assertSame([
            [
                'value' => 'failed',
                'label' => 'failed',
            ],
            [
                'value' => 'success',
                'label' => 'success',
            ],
        ], $result['package_filter_options']['source_sync_statuses']);
    }

    public function testBuildFiltersHiddenManualPackages(): void
    {
        $organizationDto = $this->createOrganization();
        $this->expectFacadeCalls($this->samplePackages(), $this->sampleLatestVersions(), $this->sampleSources());

        $result = $this->createBuilder()->build(
            $organizationDto,
            new PaginationCriteria(page: 1, perPage: 18),
            PackageIndexFilterCriteria::fromArray([
                'visibility' => ['private'],
                'listed'     => 'hidden',
                'source'     => 'manual',
            ]),
        );

        self::assertCount(1, $result['sections']);
        self::assertSame('acme/inactive-private', $result['sections'][0]['name']);
        self::assertSame(1, $result['pagination']['total']);
    }

    /**
     * @param list<PackageDto>      $packages
     * @param array<string, string> $latestVersions
     * @param list<SourceDto>       $sources
     */
    private function expectFacadeCalls(array $packages, array $latestVersions, array $sources): void
    {
        $this->packageApiFacade
            ->expects(self::once())
            ->method('packagesForOrganization')
            ->with(self::ORG_ID)
            ->willReturn($packages)
        ;
        $this->packageApiFacade
            ->expects(self::never())
            ->method('allPackages')
        ;
        $this->releaseApiFacade
            ->expects(self::once())
            ->method('latestActiveVersions')
            ->with(self::ORG_ID)
            ->willReturn($latestVersions)
        ;
        $this->sourceApiFacade
            ->expects(self::once())
            ->method('sourcesForOrganization')
            ->with(self::ORG_ID)
            ->willReturn($sources)
        ;
    }

    /**
     * @return list<PackageDto>
     */
    private function samplePackages(): array
    {
        return [
            $this->createPackage('acme/public-one', 'public', true, 'https://github.com/acme/public-one', 'source-01', 42, 'Public package one'),
            $this->createPackage('acme/private-one', 'private', true, 'https://github.com/acme/private-one', '', 7, 'Private package one'),
            $this->createPackage('acme/inactive-public', 'public', false, 'https://github.com/acme/inactive-public', description: 'Inactive public package'),
            $this->createPackage('acme/inactive-private', 'private', false, 'https://github.com/acme/inactive-private', description: 'Inactive private package'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function sampleLatestVersions(): array
    {
        return [
            'acme/public-one'  => '1.0.0',
            'acme/private-one' => '0.5.0',
        ];
    }

    /**
     * @return list<SourceDto>
     */
    private function sampleSources(): array
    {
        return [
            new SourceDto(
                id: 'source-01',
                provider: 'github',
                type: 'github',
                displayName: 'Public Repo',
                canonicalUrl: 'https://github.com/acme/public-one',
                url: 'https://github.com/acme/public-one',
                owner: 'owner',
                repository: 'public-repo',
                defaultRef: 'main',
                organizationId: self::ORG_ID,
            ),
        ];
    }

    private function createPackage(
        string $name,
        string $visibility,
        bool $active,
        string $sourceUrl,
        string $sourceId = '',
        int $downloadCount = 0,
        string $description = '',
    ): PackageDto {
        return new PackageDto(
            name: $name,
            type: 'library',
            description: $description,
            visibility: $visibility,
            sourceType: 'github',
            sourceUrl: $sourceUrl,
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-06-01T00:00:00+00:00',
            organizationId: self::ORG_ID,
            active: $active,
            sourceId: $sourceId,
            downloadCount: $downloadCount,
        );
    }

    private function createBuilder(): PackagesPageDataBuilder
    {
        return new PackagesPageDataBuilder(
            $this->packageApiFacade,
            $this->releaseApiFacade,
            $this->sourceApiFacade,
        );
    }

    private function createOrganization(bool $anonymousAccess = true): OrganizationDto
    {
        return new OrganizationDto(
            id: self::ORG_ID,
            slug: self::ORG_SLUG,
            name: self::ORG_NAME,
            anonymousAccess: $anonymousAccess,
            active: true,
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-06-15T00:00:00+00:00',
        );
    }
}
