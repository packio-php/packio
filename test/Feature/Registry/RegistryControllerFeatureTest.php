<?php

declare(strict_types=1);

namespace PackioTest\Feature\Registry;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function random_bytes;

final class RegistryControllerFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function itRequiresAuthForRegistrySetup(): void
    {
        $userDto         = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);

        $this->get('/organization/' . $organizationDto->slug . '/registry/setup')->assertRedirect('/auth/login');
    }

    #[Test]
    public function itShowsSetupPageForAuthenticatedUser(): void
    {
        $userDto         = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);
        $this->createPackage($organizationDto, 'registry/allowed-public', 'public');
        $this->createPackage($organizationDto, 'registry/private-setup', 'private');

        $this->withCsrf()->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');

        $testResponse = $this->get(
            '/organization/' . $organizationDto->slug . '/registry/setup',
            [
                'Accept'    => 'application/json',
                'X-Inertia' => 'true',
            ],
        );

        $testResponse->assertStatus(200);
        $testResponse->assertInertiaComponent('Registry/Setup');
        $testResponse->assertInertiaProp('organization.slug', $organizationDto->slug);
        $testResponse->assertInertiaProp('organization.anonymous_access', $organizationDto->anonymousAccess);
        $testResponse->assertInertiaProp('public_bootstrap', 'https://' . $organizationDto->slug . '.packio.us/packages.json');
        $testResponse->assertInertiaProp('private_bootstrap', 'https://' . $organizationDto->slug . '.packio.us/private/packages.json');
        $testResponse->assertInertiaProp('public_packages.0.name', 'registry/allowed-public');
        $testResponse->assertInertiaProp('private_packages.0.name', 'registry/private-setup');
    }

    #[Test]
    public function itShowsSetupPageForOrganizationMember(): void
    {
        $userDto         = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);
        $member          = $this->createUser();
        $this->addMember($organizationDto, $member);

        $this->withCsrf()->post('/auth/login', [
            'email'    => $member->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');

        $testResponse = $this->get(
            '/organization/' . $organizationDto->slug . '/registry/setup',
            [
                'Accept'    => 'application/json',
                'X-Inertia' => 'true',
            ],
        );

        $testResponse->assertStatus(200);
        $testResponse->assertInertiaComponent('Registry/Setup');
        $testResponse->assertInertiaProp('organization.slug', $organizationDto->slug);
    }

    #[Test]
    public function itRejectsSetupPageForAuthenticatedNonMembers(): void
    {
        $userDto         = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);
        $nonMember       = $this->createUser();

        $this->withCsrf()->post('/auth/login', [
            'email'    => $nonMember->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');

        $this->get(
            '/organization/' . $organizationDto->slug . '/registry/setup',
            [
                'X-Inertia' => 'true',
            ],
        )->assertRedirect('/organizations');
    }

    #[Test]
    public function itRedirectsMemberFromSetupOnSubdomainUiToMainApp(): void
    {
        $userDto         = $this->createUser();
        $organizationDto = $this->createOrganization($userDto);
        $member          = $this->createUser();
        $this->addMember($organizationDto, $member);

        $this->withCsrf()->post('/auth/login', [
            'email'    => $member->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');

        $testResponse = $this->withHost($organizationDto->slug . '.packio.us')->get(
            '/organization/' . $organizationDto->slug . '/registry/setup',
            [
                'Accept'    => 'application/json',
                'X-Inertia' => 'true',
            ],
        );

        $testResponse->assertStatus(409);
        self::assertStringContainsString('/organization/' . $organizationDto->slug . '/registry/setup', $testResponse->header('X-Inertia-Location'));
    }

    private function createUser(): UserDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            'registry-controller-' . bin2hex(random_bytes(6)) . '@example.test',
            'Registry Controller User',
            self::PASSWORD,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function createOrganization(UserDto $userDto): OrganizationDto
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        return $organizations->registerOrganization(
            'registry-setup-org-' . bin2hex(random_bytes(6)),
            $userDto->id,
        );
    }

    private function addMember(OrganizationDto $organizationDto, UserDto $userDto): void
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $organizations->addMember($organizationDto->id, $userDto->id, 'member');
    }

    private function createPackage(OrganizationDto $organizationDto, string $name, string $visibility): void
    {
        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);

        $packages->upsert(new PackageDto(
            name: $name,
            type: 'library',
            description: 'Package for setup page test',
            visibility: $visibility,
            sourceType: 'github',
            sourceUrl: 'https://github.com/' . $name,
            createdAt: '2026-05-20T00:00:00+00:00',
            updatedAt: '2026-05-20T00:00:00+00:00',
            organizationId: $organizationDto->id,
        ));
    }
}
