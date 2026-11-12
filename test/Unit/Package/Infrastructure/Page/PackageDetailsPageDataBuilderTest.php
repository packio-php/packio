<?php

declare(strict_types=1);

namespace PackioTest\Unit\Package\Infrastructure\Page;

use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\Policy\PackagePolicy;
use Packio\Package\Infrastructure\Page\PackageDetailsPageDataBuilder;
use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(PackageDetailsPageDataBuilder::class)]
#[AllowMockObjectsWithoutExpectations]
final class PackageDetailsPageDataBuilderTest extends TestCase
{
    private const string ORG_ID   = 'org-01';
    private const string ORG_SLUG = 'acme';
    private const string ORG_NAME = 'Acme Corp';

    /** @var MockObject&ReleaseApiFacadeInterface */
    private ReleaseApiFacadeInterface $releaseApiFacade;

    /** @var MockObject&SourceApiFacadeInterface */
    private SourceApiFacadeInterface $sourceApiFacade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->releaseApiFacade = $this->createMock(ReleaseApiFacadeInterface::class);
        $this->sourceApiFacade  = $this->createMock(SourceApiFacadeInterface::class);
    }

    public function testBuildReturnsExpectedStructure(): void
    {
        $organizationDto       = $this->createOrganization();
        $packageDto            = $this->createPublicPackage();
        $organizationMemberDto = $this->createOwnerMembership();

        $releases = [
            new ReleaseDto(
                packageName: 'acme/public-one',
                version: '1.0.0',
                reference: 'abc123',
                composer: [],
                source: [
                    'url' => 'https://github.com/acme/public-one',
                ],
                dist: [
                    'url' => 'https://dist.packio.us/acme/public-one/1.0.0',
                ],
                createdAt: '2024-06-01T00:00:00+00:00',
                organizationId: self::ORG_ID,
                releasedAt: '2024-05-01T12:00:00+00:00',
                active: true,
                downloadCount: 10,
            ),
        ];

        $this->releaseApiFacade
            ->expects(self::once())
            ->method('releasesForPackage')
            ->with('acme/public-one', true, self::ORG_ID)
            ->willReturn($releases)
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('findSource')
            ->with('source-01')
            ->willReturn($this->createSource(displayName: 'Public Repo'))
        ;

        $result = $this->createBuilder()->build($organizationDto, $packageDto, $organizationMemberDto);

        // can_manage_packages
        self::assertTrue($result['can_manage_packages']);

        // packageData
        $packageData = $result['packageData'];
        self::assertSame('acme/public-one', $packageData['name']);
        self::assertSame('library', $packageData['type']);
        self::assertSame('public', $packageData['visibility']);
        self::assertTrue($packageData['active']);
        self::assertTrue($packageData['anonymous_access']);
        self::assertSame('Available without authentication.', $packageData['access_hint']);

        // source_summary
        self::assertNotNull($packageData['source_summary']);
        self::assertSame('owner/public-repo', $packageData['source_summary']['name']);

        // releases
        self::assertCount(1, $result['releases']);
        self::assertSame('1.0.0', $result['releases'][0]['version']);
        self::assertTrue($result['releases'][0]['active']);
        self::assertSame(10, $result['releases'][0]['download_count']);
        self::assertSame('2024-05-01T12:00:00+00:00', $result['releases'][0]['released_at']);
        self::assertSame('2024-06-01T00:00:00+00:00', $result['releases'][0]['created_at']);
    }

    public function testPrivatePackageAccessHint(): void
    {
        $organizationDto = $this->createOrganization();
        $packageDto      = new PackageDto(
            name: 'acme/private-pkg',
            type: 'library',
            description: 'Private package',
            visibility: 'private',
            sourceType: 'github',
            sourceUrl: 'https://github.com/acme/private-pkg',
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-06-01T00:00:00+00:00',
            organizationId: self::ORG_ID,
            active: true,
        );

        $this->releaseApiFacade
            ->expects(self::once())
            ->method('releasesForPackage')
            ->willReturn([])
        ;

        $this->sourceApiFacade
            ->expects(self::never())
            ->method('findSource')
        ;

        $result = $this->createBuilder()->build($organizationDto, $packageDto, null);

        $packageData = $result['packageData'];
        self::assertSame('Requires registry token.', $packageData['access_hint']);
    }

    public function testAccessHintWhenAnonymousAccessDisabled(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $packageDto      = $this->createPublicPackage();

        $this->releaseApiFacade
            ->expects(self::once())
            ->method('releasesForPackage')
            ->willReturn([])
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('findSource')
            ->willReturn(null)
        ;

        $result = $this->createBuilder()->build($organizationDto, $packageDto, null);

        self::assertSame(
            'Requires registry token until anonymous access is enabled for this organization.',
            $result['packageData']['access_hint'],
        );
    }

    public function testSourceSummaryExcludedWhenFromDifferentOrganization(): void
    {
        $organizationDto = $this->createOrganization();
        $packageDto      = $this->createPublicPackage();

        $this->releaseApiFacade
            ->expects(self::once())
            ->method('releasesForPackage')
            ->willReturn([])
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('findSource')
            ->with('source-01')
            ->willReturn(
                new SourceDto(
                    id: 'source-01',
                    provider: 'github',
                    type: 'github',
                    displayName: 'Other Repo',
                    canonicalUrl: 'https://github.com/other/repo',
                    url: 'https://github.com/other/repo',
                    owner: 'other',
                    repository: 'repo',
                    defaultRef: 'main',
                    organizationId: 'org-other',
                ),
            )
        ;

        $result = $this->createBuilder()->build($organizationDto, $packageDto, null);

        self::assertNull($result['packageData']['source_summary']);
    }

    public function testCanManagePackagesFalseForMember(): void
    {
        $organizationDto       = $this->createOrganization();
        $packageDto            = $this->createPublicPackage();
        $organizationMemberDto = $this->createMemberMembership();

        $this->releaseApiFacade
            ->expects(self::once())
            ->method('releasesForPackage')
            ->willReturn([])
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('findSource')
            ->willReturn(null)
        ;

        $result = $this->createBuilder()->build($organizationDto, $packageDto, $organizationMemberDto);

        self::assertFalse($result['can_manage_packages']);
    }

    public function testInactivePackageAccessHint(): void
    {
        $organizationDto = $this->createOrganization();
        $packageDto      = new PackageDto(
            name: 'acme/inactive',
            type: 'library',
            description: 'Inactive package',
            visibility: 'public',
            sourceType: 'github',
            sourceUrl: 'https://github.com/acme/inactive',
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-06-01T00:00:00+00:00',
            organizationId: self::ORG_ID,
            active: false,
        );

        $this->releaseApiFacade
            ->expects(self::once())
            ->method('releasesForPackage')
            ->willReturn([])
        ;

        $this->sourceApiFacade
            ->expects(self::never())
            ->method('findSource')
        ;

        $result = $this->createBuilder()->build($organizationDto, $packageDto, null);

        self::assertSame('Hidden from registry.', $result['packageData']['access_hint']);
    }

    public function testPackageDataOmitsUnusedFields(): void
    {
        $this->releaseApiFacade->method('releasesForPackage')->willReturn([]);
        $this->sourceApiFacade->method('findSource')->willReturn($this->createSource());

        $packageData = $this->createBuilder()
            ->build($this->createOrganization(), $this->createPublicPackage(), null)['packageData']
        ;

        foreach (['install', 'registry_endpoint', 'organization', 'source_id'] as $key) {
            self::assertArrayNotHasKey($key, $packageData);
        }

        self::assertArrayHasKey('access_hint', $packageData);
        self::assertNotNull($packageData['source_summary']);
    }

    private function createBuilder(): PackageDetailsPageDataBuilder
    {
        return new PackageDetailsPageDataBuilder(
            $this->releaseApiFacade,
            $this->sourceApiFacade,
            new PackagePolicy(),
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

    private function createPublicPackage(): PackageDto
    {
        return new PackageDto(
            name: 'acme/public-one',
            type: 'library',
            description: 'Public package',
            visibility: 'public',
            sourceType: 'github',
            sourceUrl: 'https://github.com/acme/public-one',
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-06-01T00:00:00+00:00',
            organizationId: self::ORG_ID,
            active: true,
            sourceId: 'source-01',
            downloadCount: 42,
        );
    }

    private function createSource(string $id = 'source-01', string $displayName = 'Public Repo', string $organizationId = ''): SourceDto
    {
        return new SourceDto(
            id: $id,
            provider: 'github',
            type: 'github',
            displayName: $displayName,
            canonicalUrl: 'https://github.com/owner/repo',
            url: 'https://github.com/owner/repo',
            owner: 'owner',
            repository: 'public-repo',
            defaultRef: 'main',
            organizationId: '' !== $organizationId ? $organizationId : self::ORG_ID,
        );
    }

    private function createOwnerMembership(): OrganizationMemberDto
    {
        return new OrganizationMemberDto(
            id: 'member-01',
            organizationId: self::ORG_ID,
            userId: 'user-01',
            role: 'owner',
        );
    }

    private function createMemberMembership(): OrganizationMemberDto
    {
        return new OrganizationMemberDto(
            id: 'member-02',
            organizationId: self::ORG_ID,
            userId: 'user-02',
            role: 'member',
        );
    }
}
