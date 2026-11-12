<?php

declare(strict_types=1);

namespace PackioTest\Feature\Package;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_column;
use function array_filter;
use function array_values;
use function bin2hex;
use function explode;
use function random_bytes;
use function str_replace;
use function ucfirst;

final class PackageFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function itRedirectsAnonymousUserFromPackagesIndexToLogin(): void
    {
        [, $organization] = $this->createOrganizationWithPackages();

        $this->get('/organization/' . $organization->slug . '/packages')
            ->assertRedirect('/auth/login')
        ;
    }

    #[Test]
    public function itLoadsPackagesIndexForOrganizationMember(): void
    {
        [$user, $organization, $publicPackage] = $this->createOrganizationWithPackages();
        $this->createSourceForPackage($organization, $publicPackage);

        $this->logIn($user->email);

        $testResponse = $this->get('/organization/' . $organization->slug . '/packages', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Packages/Index');

        $sections = $testResponse->json()['props']['sections'] ?? [];
        self::assertIsArray($sections);

        $names = array_column($sections, 'name');
        self::assertContains($publicPackage, $names);
        self::assertContains('vendor/private-in-index', $names);
    }

    #[Test]
    public function itShowsLatestActiveVersionOnPackagesIndex(): void
    {
        [$user, $organization, $publicPackage] = $this->createOrganizationWithPackages();
        foreach (['1.0.0', '1.10.0', '1.9.0', 'dev-main'] as $version) {
            $this->publishRelease($publicPackage, $organization->id, $version);
        }

        $this->logIn($user->email);

        $testResponse = $this->get('/organization/' . $organization->slug . '/packages', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200);
        $sections = $testResponse->json()['props']['sections'] ?? [];
        self::assertIsArray($sections);
        $latestVersions = array_column($sections, 'latest_version', 'name');

        self::assertSame('1.10.0', $latestVersions[$publicPackage] ?? '');
        self::assertArrayHasKey('vendor/private-in-index', $latestVersions);
        self::assertNull($latestVersions['vendor/private-in-index']);
    }

    #[Test]
    public function itRedirectsAuthenticatedNonMemberFromPackagesIndex(): void
    {
        [, $organization] = $this->createOrganizationWithPackages();
        $userDto          = $this->createVerifiedUser('outsider');

        $this->logIn($userDto->email);

        $this->get('/organization/' . $organization->slug . '/packages', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->assertRedirect('/organizations');
    }

    #[Test]
    public function itDisplaysSourceSummaryOnPackagesIndex(): void
    {
        [$user, $organization, $packageName] = $this->createOrganizationWithPackages();
        [$sourceId, $sourceName]             = $this->createSourceForPackage($organization, $packageName, 0);

        $this->logIn($user->email);

        $testResponse = $this->get('/organization/' . $organization->slug . '/packages', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Packages/Index');

        $sections = $testResponse->json()['props']['sections'] ?? [];
        $sections = array_values(array_filter(
            $sections,
            static fn (array $section): bool => ($section['name'] ?? '') === $packageName,
        ));

        self::assertCount(1, $sections);
        self::assertIsArray($sections[0]['source'] ?? null);
        self::assertSame($sourceId, $sections[0]['source']['id'] ?? null);
        self::assertSame($sourceName, $sections[0]['source']['name'] ?? null);
    }

    #[Test]
    public function itShowsPrivatePackageForOrganizationMembers(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto);
        $packageName     = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackage(
            $organizationDto,
            $packageName,
            visibility: 'private',
        );
        $this->publishRelease($packageName, $organizationDto->id);

        $this->logIn($userDto->email);

        $testResponse = $this->get('/organization/' . $organizationDto->slug . '/packages/' . $packageName, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Packages/Show');
        self::assertSame('private', $testResponse->json()['props']['packageData']['visibility'] ?? null);
        self::assertSame($packageName, $testResponse->json()['props']['packageData']['name'] ?? null);
        self::assertSame('Requires registry token.', $testResponse->json()['props']['packageData']['access_hint'] ?? null);
        self::assertSame('2024-04-01T12:00:00+00:00', $testResponse->json()['props']['releases'][0]['released_at'] ?? null);
        self::assertNotEmpty($testResponse->json()['props']['releases'][0]['created_at'] ?? null);
        self::assertNotSame(
            $testResponse->json()['props']['releases'][0]['released_at'] ?? null,
            $testResponse->json()['props']['releases'][0]['created_at'] ?? null,
        );
    }

    #[Test]
    public function itRedirectsAuthenticatedNonMemberFromPublicPackagePageWhenAnonymousAccessIsEnabled(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->enableAnonymousAccess($this->createOrganization($userDto));
        $packageName     = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackage($organizationDto, $packageName, visibility: 'public');
        $this->publishRelease($packageName, $organizationDto->id);
        $nonMember = $this->createVerifiedUser('outsider');

        $this->logIn($nonMember->email);

        $this->get('/organization/' . $organizationDto->slug . '/packages/' . $packageName, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->assertRedirect('/organizations');
    }

    #[Test]
    public function itRedirectsAuthenticatedNonMemberFromPublicPackagePageWhenAnonymousAccessIsDisabled(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto);
        $packageName     = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackage($organizationDto, $packageName, visibility: 'public');
        $nonMember = $this->createVerifiedUser('outsider');

        $this->logIn($nonMember->email);

        $this->get('/organization/' . $organizationDto->slug . '/packages/' . $packageName)
            ->assertRedirect('/organizations')
        ;
    }

    #[Test]
    public function itRedirectsAnonymousUserFromPrivatePackagePageToLogin(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto);
        $packageName     = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackage(
            $organizationDto,
            $packageName,
            visibility: 'private',
        );

        $this->get('/organization/' . $organizationDto->slug . '/packages/' . $packageName)
            ->assertRedirect('/auth/login')
        ;
    }

    #[Test]
    public function itRedirectsAuthenticatedNonMemberFromPrivatePackagePage(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto);
        $packageName     = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackage(
            $organizationDto,
            $packageName,
            visibility: 'private',
        );
        $member = $this->createVerifiedUser('outsider');

        $this->logIn($member->email);

        $this->get('/organization/' . $organizationDto->slug . '/packages/' . $packageName)
            ->assertRedirect('/organizations')
        ;
    }

    #[Test]
    public function itUsesReleaseHistoryFromRequestedOrganizationOnly(): void
    {
        $userDto            = $this->createVerifiedUser('owner');
        $organizationDto    = $this->createOrganization($userDto);
        $secondOwner        = $this->createVerifiedUser('second-owner');
        $secondOrganization = $this->createOrganization($secondOwner);

        $packageName = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackage($organizationDto, $packageName, visibility: 'public');
        $this->createPackage($secondOrganization, $packageName, visibility: 'public');
        $this->publishRelease($packageName, $organizationDto->id, '1.0.0');
        $this->publishRelease($packageName, $secondOrganization->id, '2.0.0');

        $this->logIn($userDto->email);

        $testResponse = $this->get('/organization/' . $organizationDto->slug . '/packages/' . $packageName, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Packages/Show');

        $versions = array_column($testResponse->json()['props']['releases'] ?? [], 'version');
        self::assertContains('1.0.0', $versions);
        self::assertNotContains('2.0.0', $versions);
    }

    #[Test]
    public function itCanHideReleaseViaReleaseActivityEndpoint(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto);
        $packageName     = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackage($organizationDto, $packageName);
        $this->publishRelease($packageName, $organizationDto->id, '1.0.0');

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/packages/' . $packageName . '/releases/activity',
            [
                'active'  => '0',
                'version' => '1.0.0',
            ],
        )->assertRedirect('/organization/' . $organizationDto->slug . '/packages/' . $packageName);

        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        $release  = $releases->findRelease($packageName, '1.0.0', $organizationDto->id);
        self::assertNotNull($release);
        self::assertFalse($release->active);
    }

    #[Test]
    public function itCanHideReleaseWithSlashInVersion(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto);
        $packageName     = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackage($organizationDto, $packageName);
        $this->publishRelease($packageName, $organizationDto->id, 'dev-feature/x');

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/packages/' . $packageName . '/releases/activity',
            [
                'active'  => '0',
                'version' => 'dev-feature/x',
            ],
        )->assertRedirect('/organization/' . $organizationDto->slug . '/packages/' . $packageName);

        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        $release  = $releases->findRelease($packageName, 'dev-feature/x', $organizationDto->id);
        self::assertNotNull($release);
        self::assertFalse($release->active);
    }

    #[Test]
    public function itRejectsInvalidVendorInReleaseActivityRoute(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto);
        $packageName     = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackage($organizationDto, $packageName);
        $this->publishRelease($packageName, $organizationDto->id, '1.0.0');

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/packages/bad%20vendor/' . explode('/', $packageName)[1] . '/releases/activity',
            [
                'active'  => '0',
                'version' => '1.0.0',
            ],
            [
                'Accept' => 'application/json',
            ],
        )->assertStatus(422);

        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        $release  = $releases->findRelease($packageName, '1.0.0', $organizationDto->id);
        self::assertNotNull($release);
        self::assertTrue($release->active);
    }

    #[Test]
    public function itDisallowsMemberToHideReleaseViaReleaseActivity(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $packageName                     = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackage($organization, $packageName);
        $this->publishRelease($packageName, $organization->id, '1.0.0');

        $this->logIn($member->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/packages/' . $packageName . '/releases/activity',
            [
                'active'  => '0',
                'version' => '1.0.0',
            ],
        )->assertRedirect('/organization/' . $organization->slug);

        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        $release  = $releases->findRelease($packageName, '1.0.0', $organization->id);
        self::assertNotNull($release);
        self::assertTrue($release->active);
    }

    #[Test]
    public function itRedirectsUnauthorizedUserFromReleaseActivityEndpoint(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto);
        $packageName     = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackage($organizationDto, $packageName);
        $this->publishRelease($packageName, $organizationDto->id, '1.0.0');

        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/packages/' . $packageName . '/releases/activity',
            [
                'active'  => '0',
                'version' => '1.0.0',
            ],
        )->assertRedirect('/auth/login');
    }

    #[Test]
    public function itDoesNotHideReleaseFromAnotherOrganization(): void
    {
        $userDto             = $this->createVerifiedUser('owner');
        $organizationDto     = $this->createOrganization($userDto);
        $otherOwner          = $this->createVerifiedUser('other-owner');
        $foreignOrganization = $this->createOrganization($otherOwner);
        $packageName         = 'vendor/' . bin2hex(random_bytes(6));

        $this->createPackage($foreignOrganization, $packageName);
        $this->publishRelease($packageName, $foreignOrganization->id, '1.0.0');

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/packages/' . $packageName . '/releases/activity',
            [
                'active'  => '0',
                'version' => '1.0.0',
            ],
        )->assertRedirect('/organization/' . $organizationDto->slug . '/packages');

        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        $release  = $releases->findRelease($packageName, '1.0.0', $foreignOrganization->id);
        self::assertNotNull($release);
        self::assertTrue($release->active);
    }

    #[Test]
    public function itCanHidePackagesBySourceActivityEndpoint(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto);
        $sourceDto       = $this->createSource($organizationDto);
        $packageName     = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackageForSource($organizationDto, $sourceDto, $packageName);

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/sources/' . $sourceDto->id . '/packages/activity',
            [
                'active' => '0',
            ],
        )->assertRedirect('/organization/' . $organizationDto->slug . '/sources/' . $sourceDto->id);

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        $package  = $packages->findPackageInOrganization($organizationDto->id, $packageName);
        self::assertNotNull($package);
        self::assertFalse($package->active);
    }

    #[Test]
    public function itDisallowsMemberToHidePackagesBySourceActivityEndpoint(): void
    {
        [, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $sourceDto                 = $this->createSource($organization);
        $packageName               = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackageForSource($organization, $sourceDto, $packageName);

        $this->logIn($member->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/packages/activity',
            [
                'active' => '0',
            ],
        )->assertRedirect('/organization/' . $organization->slug);

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        $package  = $packages->findPackageInOrganization($organization->id, $packageName);
        self::assertNotNull($package);
        self::assertTrue($package->active);
    }

    /**
     * @return array{0: UserDto, 1: OrganizationDto, 2: string}
     */
    private function createOrganizationWithPackages(bool $includeSourceSummary = false): array
    {
        $userDto           = $this->createVerifiedUser('owner');
        $organizationDto   = $this->createOrganization($userDto);
        $publicPackageName = 'vendor/public-in-index-' . bin2hex(random_bytes(4));
        $this->createPackage($organizationDto, 'vendor/private-in-index', visibility: 'private');

        if ($includeSourceSummary) {
            $source = $this->createSource($organizationDto);
            $this->createPackageForSource($organizationDto, $source, $publicPackageName);
        } else {
            $this->createPackage($organizationDto, $publicPackageName, visibility: 'public');
        }

        return [$userDto, $organizationDto, $publicPackageName];
    }

    private function createPackage(
        OrganizationDto $organizationDto,
        string $packageName,
        string $visibility = 'public',
        bool $active = true,
        ?string $sourceId = null,
    ): void {
        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        $packages->upsert(new PackageDto(
            name: $packageName,
            type: 'library',
            description: 'Package for feature tests',
            visibility: $visibility,
            sourceType: 'github',
            sourceUrl: 'https://github.com/org/' . str_replace('/', '-', $packageName),
            createdAt: '2026-05-20T00:00:00+00:00',
            updatedAt: '2026-05-20T00:00:00+00:00',
            organizationId: $organizationDto->id,
            active: $active,
            sourceId: $sourceId ?? '',
        ));
    }

    private function publishRelease(string $packageName, string $organizationId, string $version = '1.0.0'): void
    {
        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        $releases->publish(new ReleaseDto(
            packageName: $packageName,
            version: $version,
            reference: 'v' . $version,
            composer: [
                'name'    => $packageName,
                'require' => [],
            ],
            source: [
                'url' => 'https://example.test/' . $packageName,
            ],
            dist: [
                'url' => 'https://example.test/' . $packageName . '.zip',
            ],
            createdAt: '2026-01-01T10:00:00+00:00',
            organizationId: $organizationId,
            releasedAt: '2024-04-01T12:00:00+00:00',
        ));
    }

    /**
     * @return array{0: UserDto, 1: UserDto, 2: OrganizationDto}
     */
    private function createOrganizationWithOwnerAndMember(): array
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto);
        $member          = $this->createVerifiedUser('member');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $organizations->addMember($organizationDto->id, $member->id, 'member');

        return [$userDto, $member, $organizationDto];
    }

    private function createOrganization(UserDto $userDto): OrganizationDto
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        return $organizations->registerOrganization(
            'pkg-org-' . bin2hex(random_bytes(6)),
            $userDto->id,
        );
    }

    private function enableAnonymousAccess(OrganizationDto $organizationDto): OrganizationDto
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        return $organizations->updateOrganization(
            $organizationDto->id,
            $organizationDto->name,
            $organizationDto->slug,
            true,
        );
    }

    private function createVerifiedUser(string $prefix): UserDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            $prefix . '-' . bin2hex(random_bytes(4)) . '@example.test',
            ucfirst($prefix) . ' User',
            self::PASSWORD,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function createSource(OrganizationDto $organizationDto, ?string $name = null): SourceDto
    {
        /** @var SourceApiFacadeInterface $sources */
        $sources  = $this->getService(SourceApiFacadeInterface::class);
        $resource = $name ?? ('org/' . bin2hex(random_bytes(6)));

        return $sources->registerSource(new SourceRegistrationRequestDto(
            'github',
            'https://github.com/' . $resource,
            $organizationDto->id,
        ));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createSourceForPackage(OrganizationDto $organizationDto, string $packageName, int $index = 0): array
    {
        $repository = 'org/source-for-' . $index . '-' . str_replace('/', '-', $packageName);
        $sourceDto  = $this->createSource($organizationDto, $repository);
        $this->createPackage(
            $organizationDto,
            $packageName,
            visibility: 'public',
            sourceId: $sourceDto->id,
        );

        $owner = $sourceDto->owner ?: ('owner/' . $sourceDto->id);
        $name  = $owner . '/' . $sourceDto->repository;

        return [$sourceDto->id, $name];
    }

    private function createPackageForSource(
        OrganizationDto $organizationDto,
        SourceDto $sourceDto,
        string $packageName,
        string $visibility = 'public'
    ): void {
        $this->createPackage(
            $organizationDto,
            $packageName,
            visibility: $visibility,
            sourceId: $sourceDto->id,
        );
    }

    private function logIn(string $email): void
    {
        $this->withCsrf()->post('/auth/login', [
            'email'    => $email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');
    }
}
