<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Page;

use Common\Branding\BrandingConfig;
use Packio\Registry\Api\RegistryApiFacadeInterface;
use Packio\Registry\Infrastructure\Page\RegistrySetupPageDataBuilder;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegistrySetupPageDataBuilder::class)]
#[AllowMockObjectsWithoutExpectations]
final class RegistrySetupPageDataBuilderTest extends TestCase
{
    private const string ORG_ID   = 'org-01';
    private const string ORG_SLUG = 'acme';
    private const string ORG_NAME = 'Acme Corp';

    private BrandingConfig $brandingConfig;

    /** @var MockObject&RegistryApiFacadeInterface */
    private RegistryApiFacadeInterface $registryApiFacade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brandingConfig = new BrandingConfig([
            'domain'                    => 'packio.us',
            'registry_base_domain'      => 'packio.us',
            'app_url'                   => 'https://app.packio.us',
            'composer_repository_alias' => 'packio',
            'short_name'                => 'packio',
            'product_name'              => 'Packio',
        ]);

        $this->registryApiFacade = $this->createMock(RegistryApiFacadeInterface::class);
    }

    public function testBuildReturnsExpectedStructureForAuthenticatedUser(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: true);
        $userDto         = $this->createUser();

        $this->registryApiFacade
            ->expects(self::once())
            ->method('packageOverview')
            ->with(self::ORG_ID)
            ->willReturn($this->samplePackages())
        ;

        $result = $this->createBuilder()->build($organizationDto, $userDto);

        self::assertSame('https://acme.packio.us', $result['base_url']);
        self::assertSame('acme.packio.us', $result['registry_host']);
        self::assertArrayNotHasKey('composer_repository_alias', $result);
        self::assertSame('https://acme.packio.us/packages.json', $result['public_bootstrap']);
        self::assertSame('https://acme.packio.us/private/packages.json', $result['private_bootstrap']);

        // Organization payload should use snake_case for frontend
        self::assertSame(self::ORG_ID, $result['organization']['id']);
        self::assertSame(self::ORG_SLUG, $result['organization']['slug']);
        self::assertSame(self::ORG_NAME, $result['organization']['name']);
        self::assertTrue($result['organization']['anonymous_access']);
        self::assertTrue($result['organization']['active']);
        self::assertSame('2024-01-01T00:00:00+00:00', $result['organization']['created_at']);
        self::assertSame('2024-06-15T00:00:00+00:00', $result['organization']['updated_at']);

        // Only active public packages should be included
        self::assertCount(2, $result['public_packages']);
        self::assertSame('acme/public-one', $result['public_packages'][0]['name']);
        self::assertSame('1.0.0', $result['public_packages'][0]['latest_version']);
        self::assertSame('acme/public-two', $result['public_packages'][1]['name']);
        self::assertSame('2.1.0', $result['public_packages'][1]['latest_version']);

        // Authenticated user should see active private packages
        self::assertCount(2, $result['private_packages']);
        self::assertSame('acme/private-one', $result['private_packages'][0]['name']);
        self::assertSame('0.5.0', $result['private_packages'][0]['latest_version']);
        self::assertSame('acme/private-two', $result['private_packages'][1]['name']);
        self::assertSame('3.0.0', $result['private_packages'][1]['latest_version']);
    }

    public function testBuildReturnsNullPrivateBootstrapForAnonymousUser(): void
    {
        $organizationDto = $this->createOrganization();
        $user            = null;

        $this->registryApiFacade
            ->expects(self::once())
            ->method('packageOverview')
            ->with(self::ORG_ID)
            ->willReturn($this->samplePackages())
        ;

        $result = $this->createBuilder()->build($organizationDto, $user);

        self::assertNull($result['private_bootstrap']);
        self::assertCount(0, $result['private_packages']);
        self::assertCount(2, $result['public_packages']);
    }

    public function testBuildExcludesInactivePackages(): void
    {
        $organizationDto = $this->createOrganization();
        $userDto         = $this->createUser();

        // Only inactive packages
        $this->registryApiFacade
            ->expects(self::once())
            ->method('packageOverview')
            ->with(self::ORG_ID)
            ->willReturn([
                [
                    'name'              => 'acme/inactive-public',
                    'description'       => null,
                    'latest_version'    => null,
                    'visibility'        => 'public',
                    'active'            => false,
                    'source_url'        => 'https://github.com/acme/inactive-public',
                    'registry_endpoint' => 'https://acme.packio.us/packages.json',
                    'access_hint'       => 'public',
                ],
                [
                    'name'              => 'acme/inactive-private',
                    'description'       => null,
                    'latest_version'    => null,
                    'visibility'        => 'private',
                    'active'            => false,
                    'source_url'        => 'https://github.com/acme/inactive-private',
                    'registry_endpoint' => 'https://acme.packio.us/private/packages.json',
                    'access_hint'       => 'private',
                ],
            ])
        ;

        $result = $this->createBuilder()->build($organizationDto, $userDto);

        self::assertCount(0, $result['public_packages']);
        self::assertCount(0, $result['private_packages']);
    }

    public function testBuildMapsOrganizationAnonymousAccess(): void
    {
        $organizationDto = $this->createOrganization(anonymousAccess: false);
        $userDto         = $this->createUser();

        $this->registryApiFacade
            ->method('packageOverview')
            ->willReturn([])
        ;

        $result = $this->createBuilder()->build($organizationDto, $userDto);

        self::assertFalse($result['organization']['anonymous_access']);

        // Build again with anonymousAccess enabled
        $orgAnonymous = $this->createOrganization(anonymousAccess: true);

        $resultAnonymous = $this->createBuilder()->build($orgAnonymous, $userDto);

        self::assertTrue($resultAnonymous['organization']['anonymous_access']);
    }

    private function createBuilder(): RegistrySetupPageDataBuilder
    {
        return new RegistrySetupPageDataBuilder(
            $this->registryApiFacade,
            $this->brandingConfig,
        );
    }

    private function createOrganization(bool $anonymousAccess = false): OrganizationDto
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

    private function createUser(): UserDto
    {
        return new UserDto(
            id: 'user-01',
            email: 'user@example.com',
            displayName: 'Test User',
            roles: ['user'],
            createdAt: '2024-01-01T00:00:00+00:00',
        );
    }

    /**
     * @return list<array{name: string, description: null|string, latest_version: null|string, visibility: string, active: bool, source_url: string, registry_endpoint: string, access_hint: string}>
     */
    private function samplePackages(): array
    {
        return [
            [
                'name'              => 'acme/public-one',
                'description'       => 'Public package one',
                'latest_version'    => '1.0.0',
                'visibility'        => 'public',
                'active'            => true,
                'source_url'        => 'https://github.com/acme/public-one',
                'registry_endpoint' => 'https://acme.packio.us/packages.json',
                'access_hint'       => 'public',
            ],
            [
                'name'              => 'acme/public-two',
                'description'       => 'Public package two',
                'latest_version'    => '2.1.0',
                'visibility'        => 'public',
                'active'            => true,
                'source_url'        => 'https://github.com/acme/public-two',
                'registry_endpoint' => 'https://acme.packio.us/packages.json',
                'access_hint'       => 'public',
            ],
            [
                'name'              => 'acme/private-one',
                'description'       => 'Private package one',
                'latest_version'    => '0.5.0',
                'visibility'        => 'private',
                'active'            => true,
                'source_url'        => 'https://github.com/acme/private-one',
                'registry_endpoint' => 'https://acme.packio.us/private/packages.json',
                'access_hint'       => 'private',
            ],
            [
                'name'              => 'acme/private-two',
                'description'       => 'Private package two',
                'latest_version'    => '3.0.0',
                'visibility'        => 'private',
                'active'            => true,
                'source_url'        => 'https://github.com/acme/private-two',
                'registry_endpoint' => 'https://acme.packio.us/private/packages.json',
                'access_hint'       => 'private',
            ],
            [
                'name'              => 'acme/inactive-public',
                'description'       => 'Inactive public package',
                'latest_version'    => null,
                'visibility'        => 'public',
                'active'            => false,
                'source_url'        => 'https://github.com/acme/inactive-public',
                'registry_endpoint' => 'https://acme.packio.us/packages.json',
                'access_hint'       => 'public',
            ],
            [
                'name'              => 'acme/inactive-private',
                'description'       => 'Inactive private package',
                'latest_version'    => null,
                'visibility'        => 'private',
                'active'            => false,
                'source_url'        => 'https://github.com/acme/inactive-private',
                'registry_endpoint' => 'https://acme.packio.us/private/packages.json',
                'access_hint'       => 'private',
            ],
        ];
    }
}
