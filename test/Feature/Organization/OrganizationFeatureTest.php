<?php

declare(strict_types=1);

namespace PackioTest\Feature\Organization;

use Common\Branding\BrandingConfig;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Admin\Application\Settings\RuntimeSettingOverride;
use Packio\Admin\Application\Settings\RuntimeSettingsService;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\Dto\OrganizationInvitationDto;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Application\Command\OrganizationDeletionService;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Builder\ReleaseBuilder;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

use function array_column;
use function array_filter;
use function array_first;
use function bin2hex;
use function count;
use function preg_replace;
use function random_bytes;
use function rawurlencode;
use function rtrim;
use function strtolower;
use function trim;

final class OrganizationFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    private FakeOrganizationArchiveStorageCleanupApiFacade $fakeOrganizationArchiveStorageCleanupApiFacade;

    protected function setUp(): void
    {
        $this->fakeOrganizationArchiveStorageCleanupApiFacade                   = new FakeOrganizationArchiveStorageCleanupApiFacade();
        $this->serviceOverrides[ArchiveStorageCleanupApiFacadeInterface::class] = $this->fakeOrganizationArchiveStorageCleanupApiFacade;

        parent::setUp();
    }

    #[Test]
    public function itDisplaysOrganizationsIndexForAuthenticatedUser(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto, 'feature-org-index-' . $this->suffix());

        $this->logIn($userDto->email);

        $testResponse = $this->get('/organizations', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Organizations/Index');
        self::assertTrue($testResponse->json()['props']['has_organizations'] ?? false);
        self::assertContains($organizationDto->name, array_column($testResponse->json()['props']['organizations'] ?? [], 'name'));
    }

    #[Test]
    public function itRedirectsAnonymousUserFromOrganizationsToLogin(): void
    {
        $this->get('/organizations')->assertRedirect('/auth/login');
    }

    #[Test]
    public function itCreatesOrganizationThroughAuthenticatedFormSubmission(): void
    {
        $userDto          = $this->createVerifiedUser('owner');
        $organizationName = 'Team ' . bin2hex(random_bytes(4));

        $this->logIn($userDto->email);

        $this->withCsrf()->post('/organizations', [
            'name' => $organizationName,
        ])->assertRedirect('/organization/' . $this->slugFromName($organizationName) . '/packages');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $created       = $organizations->findOrganizationBySlug($this->slugFromName($organizationName));
        self::assertNotNull($created);
        self::assertSame($organizationName, $created->name);
    }

    #[Test]
    public function itRejectsDuplicateOrganizationNameAndDoesNotCreateSecondOrganization(): void
    {
        $userDto          = $this->createVerifiedUser('owner');
        $organizationName = 'Duplicate ' . bin2hex(random_bytes(4));
        $this->createOrganization($userDto, $organizationName);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $before        = count($organizations->allOrganizations());

        $this->logIn($userDto->email);
        $this->withCsrf()->post('/organizations', [
            'name' => $organizationName,
        ])->assertRedirect('/organizations');

        $after = count($organizations->allOrganizations());
        self::assertSame($before, $after);
    }

    #[Test]
    public function itRejectsReservedOrganizationNameAndDoesNotCreateOrganization(): void
    {
        $userDto = $this->createVerifiedUser('owner');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $before        = count($organizations->allOrganizations());

        $this->logIn($userDto->email);
        $this->withCsrf()->post('/organizations', [
            'name' => 'Admin',
        ])->assertRedirect('/organizations');

        $this->get('/organizations', [
            'X-Inertia' => 'true',
        ])
            ->assertStatus(200)
            ->assertInertiaComponent('Organizations/Index')
            ->assertJsonPath('props.errors.name.0', 'Organization name is reserved.')
            ->assertJsonPath('props.old.name', 'Admin')
        ;

        self::assertSame($before, count($organizations->allOrganizations()));
        self::assertNull($organizations->findOrganizationBySlug('admin'));
    }

    #[Test]
    public function itRedirectsOrganizationRootRouteToPackagesPage(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto, 'redirect-route-org-' . $this->suffix());

        $this->logIn($userDto->email);

        $this->get('/organization/' . $organizationDto->slug)
            ->assertRedirect('/organization/' . $organizationDto->slug . '/packages')
        ;
    }

    #[Test]
    public function itRedirectsOrganizationRootRouteToOrganizationsForAuthenticatedNonMember(): void
    {
        $owner           = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($owner, 'redirect-route-public-' . $this->suffix());
        $userDto         = $this->createVerifiedUser('non-member');

        $this->logIn($userDto->email);

        $this->get('/organization/' . $organizationDto->slug)
            ->assertRedirect('/organizations')
        ;
    }

    #[Test]
    public function itRendersOrganizationSettingsPageForMember(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        $this->logIn($member->email);

        $testResponse = $this->get('/organization/' . $organization->slug . '/settings', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Organization/Settings');
        self::assertSame($organization->name, $testResponse->json()['props']['settings']['name'] ?? null);
        self::assertSame($organization->slug, $testResponse->json()['props']['settings']['route_key'] ?? null);
        self::assertSame(2, $testResponse->json()['props']['members_count'] ?? null);
    }

    #[Test]
    public function itRejectsOrganizationSettingsPageForAuthenticatedNonMembers(): void
    {
        $owner           = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($owner, 'settings-non-member-' . $this->suffix());
        $userDto         = $this->createVerifiedUser('non-member');

        $this->logIn($userDto->email);

        $this->get('/organization/' . $organizationDto->slug . '/settings', [
            'X-Inertia' => 'true',
        ])->assertRedirect('/organizations');
    }

    #[Test]
    public function itRendersOrganizationMembersPageForMember(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        $this->logIn($member->email);

        $testResponse = $this->get('/organization/' . $organization->slug . '/members', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Organization/Members');
        self::assertSame($organization->id, $testResponse->json()['props']['organization']['id'] ?? null);
        self::assertCount(2, $testResponse->json()['props']['members'] ?? []);
        foreach ($testResponse->json()['props']['members'] ?? [] as $memberItem) {
            self::assertFalse($memberItem['can_remove'] ?? true);
        }
    }

    #[Test]
    public function itMarksRemovableMembersForOrganizationOwners(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        $this->logIn($owner->email);

        $testResponse = $this->get('/organization/' . $organization->slug . '/members', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Organization/Members');

        $memberItems = $testResponse->json()['props']['members'] ?? [];
        self::assertCount(2, $memberItems);

        self::assertFalse($this->memberItemByEmail($memberItems, $owner->email)['can_remove'] ?? true);
        self::assertTrue($this->memberItemByEmail($memberItems, $member->email)['can_remove'] ?? false);
    }

    #[Test]
    public function itRejectsOrganizationMembersPageForAuthenticatedNonMembers(): void
    {
        $owner           = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($owner, 'members-non-member-' . $this->suffix());
        $userDto         = $this->createVerifiedUser('non-member');

        $this->logIn($userDto->email);

        $this->get('/organization/' . $organizationDto->slug . '/members', [
            'X-Inertia' => 'true',
        ])->assertRedirect('/organizations');
    }

    #[Test]
    public function itAllowsOwnerToUpdateAnonymousAccessSetting(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto, 'owner-anon-access-' . $this->suffix());

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/settings',
            [
                'name'             => $organizationDto->name,
                'anonymous_access' => '1',
            ],
        )->assertRedirect('/organization/' . $organizationDto->slug . '/settings');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $updated       = $organizations->findOrganization($organizationDto->id);
        self::assertNotNull($updated);
        self::assertTrue($updated->anonymousAccess);
    }

    #[Test]
    public function itAllowsOwnerToUpdateOrganizationNameWithoutChangingSlug(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto, 'rename-org-' . $this->suffix());
        $newName         = 'Renamed Org ' . $this->suffix();

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/settings',
            [
                'name'             => $newName,
                'anonymous_access' => '1',
            ],
        )->assertRedirect('/organization/' . $organizationDto->slug . '/settings');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $updated       = $organizations->findOrganization($organizationDto->id);
        self::assertNotNull($updated);
        self::assertSame($newName, $updated->name);
        self::assertSame($organizationDto->slug, $updated->slug);
        self::assertTrue($updated->anonymousAccess);
    }

    #[Test]
    public function itRejectsDuplicateOrganizationNameWhenUpdatingSettings(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $otherOwner      = $this->createVerifiedUser('other-owner');
        $organizationDto = $this->createOrganization($userDto, 'primary-org-' . $this->suffix());
        $otherOrg        = $this->createOrganization($otherOwner, 'other-org-' . $this->suffix());

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/settings',
            [
                'name' => $otherOrg->name,
            ],
        )->assertRedirect('/organization/' . $organizationDto->slug . '/settings');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $reloaded      = $organizations->findOrganization($organizationDto->id);
        self::assertNotNull($reloaded);
        self::assertSame($organizationDto->name, $reloaded->name);
        self::assertSame($organizationDto->slug, $reloaded->slug);
    }

    #[Test]
    public function itRejectsReservedOrganizationNameWhenUpdatingSettings(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto, 'reserved-update-org-' . $this->suffix());

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/settings',
            [
                'name' => 'App',
            ],
        )->assertRedirect('/organization/' . $organizationDto->slug . '/settings');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $reloaded      = $organizations->findOrganization($organizationDto->id);
        self::assertNotNull($reloaded);
        self::assertSame($organizationDto->name, $reloaded->name);
        self::assertSame($organizationDto->slug, $reloaded->slug);
    }

    #[Test]
    public function itRejectsMemberFromUpdatingOrganizationSettings(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        $this->logIn($member->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/settings',
            [
                'name'             => 'Member Rename ' . $this->suffix(),
                'anonymous_access' => '1',
            ],
        )->assertRedirect('/organization/' . $organization->slug);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $reloaded      = $organizations->findOrganization($organization->id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->anonymousAccess);
    }

    #[Test]
    public function itCreatesOrganizationInvitationAsOwner(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto, 'owner-invite-' . $this->suffix());

        $this->logIn($userDto->email);
        $this->withCsrf()->post('/organization/' . $organizationDto->slug . '/invitations', [
            'role' => 'member',
        ])->assertRedirect('/organization/' . $organizationDto->slug . '/members');

        $testResponse = $this->get('/organization/' . $organizationDto->slug . '/members', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Organization/Members');
        $invitations = $testResponse->json()['props']['invitations'] ?? [];
        self::assertCount(1, $invitations);
        self::assertTrue($testResponse->json()['props']['can_manage_members'] ?? null);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations     = $this->getService(OrganizationApiFacadeInterface::class);
        $createdInvitation = $organizations->invitations($organizationDto->id)[0] ?? null;
        self::assertNotNull($createdInvitation);

        /** @var BrandingConfig $brandingConfig */
        $brandingConfig = $this->getService(BrandingConfig::class);
        self::assertSame(
            rtrim($brandingConfig->appBaseUrl(), '/') . '/organization/invite/' . rawurlencode($createdInvitation->token),
            $invitations[0]['accept_url'] ?? null,
        );
    }

    #[Test]
    public function itHidesInvitationLinksFromRegularMembers(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $this->createInvitation($organization, $owner->id, 'owner');

        $this->logIn($member->email);

        $testResponse = $this->get('/organization/' . $organization->slug . '/members', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Organization/Members');
        $props = $testResponse->json()['props'];
        self::assertSame([], $props['invitations']);
        self::assertFalse($props['can_manage_members']);
    }

    #[Test]
    public function itRedirectsGuestInvitePageToLoginWithReturnTarget(): void
    {
        $userDto                   = $this->createVerifiedUser('owner');
        $organizationDto           = $this->createOrganization($userDto, 'invite-guest-' . $this->suffix());
        $organizationInvitationDto = $this->createInvitation($organizationDto, $userDto->id, 'member');

        $this->get('/organization/invite/' . $organizationInvitationDto->token)
            ->assertRedirect('/auth/login?redirect_to=%2Forganization%2Finvite%2F' . $organizationInvitationDto->token)
        ;
    }

    #[Test]
    public function itAllowsGuestToRegisterFromPendingInvitationWhenPublicRegistrationIsClosed(): void
    {
        $userDto                   = $this->createVerifiedUser('owner');
        $organizationDto           = $this->createOrganization($userDto, 'invite-register-' . $this->suffix());
        $organizationInvitationDto = $this->createInvitation($organizationDto, $userDto->id, 'member');
        $inviteTarget              = '/organization/invite/' . $organizationInvitationDto->token;

        /** @var RuntimeSettingsService $settings */
        $settings = $this->getService(RuntimeSettingsService::class);
        $settings->updateOverrides([
            ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::Off->value,
        ], null);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertSame('pending', $organizations->findInvitationByToken($organizationInvitationDto->token)?->status);

        $testResponse = $this->get('/auth/register?redirect_to=' . rawurlencode($inviteTarget), [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200)->assertInertiaComponent('Auth/Register');
        self::assertSame($inviteTarget, $testResponse->json()['props']['redirect_to'] ?? null);
        self::assertTrue($testResponse->json()['props']['registration_open'] ?? false);

        $email = 'invitee-' . $this->suffix() . '@example.test';

        $this->withCsrf()->post('/auth/register', [
            'display_name'          => 'Invited User',
            'email'                 => $email,
            'password'              => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'redirect_to'           => $inviteTarget,
        ])->assertRedirect('/auth/verification');

        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertNotNull($identity->authenticateCredentials($email, self::PASSWORD));
    }

    #[Test]
    public function itRendersOrganizationInvitationAcceptPageForAuthenticatedUser(): void
    {
        $userDto                   = $this->createVerifiedUser('owner');
        $invitee                   = $this->createVerifiedUser('invitee');
        $organizationDto           = $this->createOrganization($userDto, 'invite-render-' . $this->suffix());
        $organizationInvitationDto = $this->createInvitation($organizationDto, $userDto->id, 'member');

        $this->logIn($invitee->email);

        $testResponse = $this->get('/organization/invite/' . $organizationInvitationDto->token, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Organization/InviteAccept');
        self::assertSame($organizationInvitationDto->token, $testResponse->json()['props']['invitation']['token'] ?? null);
        self::assertSame($organizationDto->name, $testResponse->json()['props']['organization_target']['name'] ?? null);
    }

    #[Test]
    public function itAcceptsOrganizationInvitationForAuthenticatedUser(): void
    {
        $userDto                   = $this->createVerifiedUser('owner');
        $invitee                   = $this->createVerifiedUser('invitee');
        $organizationDto           = $this->createOrganization($userDto, 'invite-accept-' . $this->suffix());
        $organizationInvitationDto = $this->createInvitation($organizationDto, $userDto->id, 'member');

        $this->logIn($invitee->email);

        $this->withCsrf()
            ->post('/organization/invite/' . $organizationInvitationDto->token . '/accept')
            ->assertRedirect('/organization/' . $organizationDto->slug . '/packages')
        ;

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertNotNull($organizations->findMembership($organizationDto->id, $invitee->id));

        $accepted = $organizations->findInvitationByToken($organizationInvitationDto->token);
        self::assertNotNull($accepted);
        self::assertSame('accepted', $accepted->status);
        self::assertSame($invitee->id, $accepted->acceptedByUserId);
    }

    #[Test]
    public function existingMemberAcceptingOwnerInvitationKeepsMemberRole(): void
    {
        [$owner, $member, $organization]                = $this->createOrganizationWithOwnerAndMember();
        $organizationInvitationDto                      = $this->createInvitation($organization, $owner->id, 'owner');

        $this->logIn($member->email);
        $this->withCsrf()->post('/organization/invite/' . $organizationInvitationDto->token . '/accept', []);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertSame('member', $organizations->findMembership($organization->id, $member->id)?->role);
        self::assertSame('accepted', $organizations->findInvitationByToken($organizationInvitationDto->token)?->status);
    }

    #[Test]
    public function newUserAcceptingOwnerInvitationBecomesOwner(): void
    {
        [$owner, , $organization]                = $this->createOrganizationWithOwnerAndMember();
        $userDto                                 = $this->createVerifiedUser('invitee');
        $organizationInvitationDto               = $this->createInvitation($organization, $owner->id, 'owner');

        $this->logIn($userDto->email);
        $this->withCsrf()->post('/organization/invite/' . $organizationInvitationDto->token . '/accept', []);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertSame('owner', $organizations->findMembership($organization->id, $userDto->id)?->role);
    }

    #[Test]
    public function itRevokesOrganizationInvitationAsOwner(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto, 'owner-invite-' . $this->suffix());

        $this->createInvitation($organizationDto, $userDto->id, 'member');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertCount(1, $organizations->invitations($organizationDto->id));
        $invitationId = $organizations->invitations($organizationDto->id)[0]->id;

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/invitations/' . $invitationId . '/revoke',
        )->assertRedirect('/organization/' . $organizationDto->slug . '/members');

        $reloadedInvitations = $organizations->invitations($organizationDto->id);
        self::assertCount(1, $reloadedInvitations);
        self::assertSame('revoked', $reloadedInvitations[0]->status);
    }

    #[Test]
    public function itRejectsMemberFromRevokingInvitation(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $this->createInvitation($organization, $owner->id, 'member');

        $this->logIn($member->email);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $invitations   = $organizations->invitations($organization->id);
        self::assertCount(1, $invitations);
        $invitationId = $invitations[0]->id;

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/invitations/' . $invitationId . '/revoke',
        )->assertRedirect('/organization/' . $organization->slug);

        $reloaded = $organizations->invitations($organization->id);
        self::assertCount(1, $reloaded);
        self::assertSame('pending', $reloaded[0]->status);
    }

    #[Test]
    public function itAllowsOwnerToRemoveMember(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        $this->logIn($owner->email);
        $memberMembership = $this->findMemberIdByUser($organization, $member->id);

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/members/' . $memberMembership . '/remove',
        )->assertRedirect('/organization/' . $organization->slug . '/members');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertNull($organizations->findMembership($organization->id, $member->id));
    }

    #[Test]
    public function itRejectsOwnerFromRemovingSelf(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $organizations->addMember($organization->id, $member->id, 'owner');

        $this->logIn($owner->email);
        $ownerMembership = $this->findMemberIdByUser($organization, $owner->id);

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/members/' . $ownerMembership . '/remove',
        )->assertRedirect('/organization/' . $organization->slug . '/members');

        $ownerMembershipDto = $organizations->findMembership($organization->id, $owner->id);
        self::assertNotNull($ownerMembershipDto);
        self::assertSame('owner', $ownerMembershipDto->role);
    }

    #[Test]
    public function itRejectsRemovingLastOwnerFromMembers(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto, 'owner-remove-self-' . $this->suffix());

        $this->logIn($userDto->email);
        $ownerMembership = $this->findMemberIdByUser($organizationDto, $userDto->id);

        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/members/' . $ownerMembership . '/remove',
        )->assertRedirect('/organization/' . $organizationDto->slug . '/members');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertNotNull($organizations->findMembership($organizationDto->id, $userDto->id));
    }

    #[Test]
    public function itAllowsOwnerToPromoteMemberToOwner(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        $this->logIn($owner->email);
        $memberMembership = $this->findMemberIdByUser($organization, $member->id);

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/members/' . $memberMembership . '/role',
            [
                'role' => 'owner',
            ],
        )->assertRedirect('/organization/' . $organization->slug . '/members');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertSame('owner', $organizations->findMembership($organization->id, $member->id)?->role);
    }

    #[Test]
    public function itAllowsOwnerToDemoteAnotherOwnerWhenAnotherOwnerRemains(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $organizations->addMember($organization->id, $member->id, 'owner');

        $this->logIn($owner->email);
        $memberMembership = $this->findMemberIdByUser($organization, $member->id);

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/members/' . $memberMembership . '/role',
            [
                'role' => 'member',
            ],
        )->assertRedirect('/organization/' . $organization->slug . '/members');

        self::assertSame('member', $organizations->findMembership($organization->id, $member->id)?->role);
    }

    #[Test]
    public function itRejectsDemotingLastOwner(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto, 'owner-demote-last-' . $this->suffix());

        $this->logIn($userDto->email);
        $ownerMembership = $this->findMemberIdByUser($organizationDto, $userDto->id);

        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/members/' . $ownerMembership . '/role',
            [
                'role' => 'member',
            ],
        )->assertRedirect('/organization/' . $organizationDto->slug . '/members');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertSame('owner', $organizations->findMembership($organizationDto->id, $userDto->id)?->role);
    }

    #[Test]
    public function itRejectsMemberFromChangingRoles(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        $this->logIn($member->email);
        $ownerMembership = $this->findMemberIdByUser($organization, $owner->id);

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/members/' . $ownerMembership . '/role',
            [
                'role' => 'member',
            ],
        )->assertRedirect('/organization/' . $organization->slug);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertSame('owner', $organizations->findMembership($organization->id, $owner->id)?->role);
    }

    #[Test]
    public function itRejectsOwnerFromChangingOwnRole(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $organizations->addMember($organization->id, $member->id, 'owner');

        $this->logIn($owner->email);
        $ownerMembership = $this->findMemberIdByUser($organization, $owner->id);

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/members/' . $ownerMembership . '/role',
            [
                'role' => 'member',
            ],
        )->assertRedirect('/organization/' . $organization->slug . '/members');

        self::assertSame('owner', $organizations->findMembership($organization->id, $owner->id)?->role);
    }

    #[Test]
    public function itRejectsChangingRoleForMemberFromAnotherOrganization(): void
    {
        $userDto           = $this->createVerifiedUser('owner');
        $otherOwner        = $this->createVerifiedUser('other-owner');
        $organizationDto   = $this->createOrganization($userDto, 'role-org-primary-' . $this->suffix());
        $otherOrganization = $this->createOrganization($otherOwner, 'role-org-other-' . $this->suffix());

        $this->logIn($userDto->email);
        $otherOwnerMembership = $this->findMemberIdByUser($otherOrganization, $otherOwner->id);

        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/members/' . $otherOwnerMembership . '/role',
            [
                'role' => 'member',
            ],
        )->assertRedirect('/organization/' . $organizationDto->slug . '/members');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertSame('owner', $organizations->findMembership($otherOrganization->id, $otherOwner->id)?->role);
    }

    #[Test]
    public function itRejectsInvalidMemberRole(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        $this->logIn($owner->email);
        $memberMembership = $this->findMemberIdByUser($organization, $member->id);

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/members/' . $memberMembership . '/role',
            [
                'role' => 'admin',
            ],
        )->assertRedirect('/organization/' . $organization->slug . '/members');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertSame('member', $organizations->findMembership($organization->id, $member->id)?->role);
    }

    #[Test]
    public function itDeletesOrganizationWithCorrectConfirmation(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto, 'delete-org-' . $this->suffix());

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertEmpty($this->findOrganizationDeleteJobs($jobs, $organizationDto->id));

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/settings/delete',
            [
                'confirmationName' => $organizationDto->name,
            ],
        )->assertRedirect('/organizations');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations      = $this->getService(OrganizationApiFacadeInterface::class);
        $queuedOrganization = $organizations->findOrganization($organizationDto->id);
        self::assertNotNull($queuedOrganization);
        self::assertSame('deleting', $queuedOrganization->status);
        self::assertFalse($queuedOrganization->active);
        self::assertNull($organizations->findOrganizationBySlug($organizationDto->slug));

        $testResponse = $this->get('/organizations', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200);
        self::assertSame(
            'Organization will be permanently deleted.',
            $testResponse->json()['flash']['success'] ?? null,
        );
        self::assertNotContains(
            $organizationDto->id,
            array_column($testResponse->json()['props']['organizations'] ?? [], 'id'),
        );

        $this->get('/organization/' . $organizationDto->slug . '/settings')
            ->assertRedirect('/organizations')
        ;

        $deleteJobs = $this->findOrganizationDeleteJobs($jobs, $organizationDto->id);
        self::assertCount(1, $deleteJobs);
        self::assertSame('organization.delete', $deleteJobs[0]->type());
        self::assertSame($organizationDto->id, $deleteJobs[0]->payload()['organization_id'] ?? null);
        self::assertSame($userDto->id, $deleteJobs[0]->payload()['requested_by_user_id'] ?? null);
        self::assertSame('schedule_cleanup', $deleteJobs[0]->payload()['stage'] ?? null);
        $deletionRunId = $deleteJobs[0]->payload()['deletion_run_id'];
        self::assertIsString($deletionRunId);
        self::assertNotSame('', $deletionRunId);
        self::assertSame(
            'organization-delete:' . $organizationDto->id . ':' . $deletionRunId,
            $deleteJobs[0]->idempotencyKey(),
        );

        /** @var AuditApiFacadeInterface $audit */
        $audit             = $this->getService(AuditApiFacadeInterface::class);
        $initiationEntries = array_filter(
            $audit->recentBySubject('organization', $organizationDto->id, 20),
            static fn ($entry): bool => 'organization.delete.initiated' === $entry->event,
        );
        self::assertCount(1, $initiationEntries);
        $initiationEntry = array_first($initiationEntries);
        self::assertNotNull($initiationEntry);
        self::assertSame($userDto->id, $initiationEntry->actorId);
        self::assertSame($deletionRunId, $initiationEntry->context['deletion_run_id'] ?? null);

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($deleteJobs[0]);

        self::assertNotNull($organizations->findOrganization($organizationDto->id));

        $finalizeJobs = $this->findOrganizationFinalizeJobs(
            $jobs,
            $organizationDto->id,
            $deletionRunId,
        );
        self::assertCount(1, $finalizeJobs);
        $jobProcessor->process($finalizeJobs[0]);

        self::assertNull($organizations->findOrganization($organizationDto->id));
    }

    #[Test]
    public function itDeletesSourceOwnedPackagesThroughSourceDeleteBeforeFinalizingOrganization(): void
    {
        $userDto         = $this->createVerifiedUser('owner-source-delete');
        $organizationDto = $this->createOrganization($userDto, 'delete-source-owned-' . $this->suffix());
        $sourceDto       = $this->createSource($organizationDto);
        $packageName     = 'vendor/source-owned-' . $this->suffix();
        $this->createPackage($organizationDto, $packageName, $sourceDto);
        $this->publishRelease($packageName, '1.0.0', $organizationDto->id);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/settings/delete',
            [
                'confirmationName' => $organizationDto->name,
            ],
        )->assertRedirect('/organizations');

        $deleteJobs    = $this->findOrganizationDeleteJobs($jobs, $organizationDto->id);
        $deletionRunId = (string) ($deleteJobs[0]->payload()['deletion_run_id'] ?? '');

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($deleteJobs[0]);

        $sourceDeleteJobs = $this->findSourceDeleteJobs($jobs, $organizationDto->id, $sourceDto->id);
        self::assertCount(1, $sourceDeleteJobs);
        self::assertSame(
            'organization-delete:' . $organizationDto->id . ':' . $deletionRunId . ':source:' . $sourceDto->id,
            $sourceDeleteJobs[0]->idempotencyKey(),
        );
        self::assertSame($deletionRunId, $sourceDeleteJobs[0]->payload()['deletion_run_id'] ?? null);
        self::assertSame('organization.delete', $sourceDeleteJobs[0]->payload()['origin'] ?? null);
        self::assertSame([], $this->findPackageDeleteJobs($jobs, $organizationDto->id, $packageName));

        $finalizeJobs = $this->findOrganizationFinalizeJobs($jobs, $organizationDto->id, $deletionRunId);
        self::assertCount(1, $finalizeJobs);
        $jobProcessor->process($finalizeJobs[0]);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertNotNull($organizations->findOrganization($organizationDto->id));

        $jobProcessor->process($sourceDeleteJobs[0]);

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        self::assertNull($sources->findSource($sourceDto->id));

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        self::assertNull($packages->findPackageInOrganization($organizationDto->id, $packageName));

        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        self::assertNull($releases->findRelease($packageName, '1.0.0', $organizationDto->id));
        self::assertSame([[$organizationDto->id, $packageName, $deletionRunId, 'organization.delete']], $this->fakeOrganizationArchiveStorageCleanupApiFacade->calls);

        $jobProcessor->process($finalizeJobs[0]);

        self::assertNull($organizations->findOrganization($organizationDto->id));
    }

    #[Test]
    public function scheduleCleanupTouchesOnlyItsOrganization(): void
    {
        $userDto          = $this->createVerifiedUser('owner-a');
        $organizationDto  = $this->createOrganization($userDto, 'cleanup-a-' . $this->suffix());
        $userB            = $this->createVerifiedUser('owner-b');
        $orgB             = $this->createOrganization($userB, 'cleanup-b-' . $this->suffix());

        $sourceDto = $this->createSource($organizationDto);
        $this->createSource($orgB);
        $packageA = 'vendor/cleanup-a-' . $this->suffix();
        $packageB = 'vendor/cleanup-b-' . $this->suffix();
        $this->createPackage($organizationDto, $packageA);
        $this->createPackage($orgB, $packageB);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/settings/delete',
            [
                'confirmationName' => $organizationDto->name,
            ],
        )->assertRedirect('/organizations');

        $deleteJob = $this->findOrganizationDeleteJobs($jobs, $organizationDto->id)[0];

        /** @var OrganizationDeletionService $deletionService */
        $deletionService = $this->getService(OrganizationDeletionService::class);
        $result          = $deletionService->scheduleCleanup(
            $organizationDto->id,
            (string) $deleteJob->payload()['deletion_run_id'],
        );

        self::assertSame(1, $result['sources_found']);
        self::assertSame(1, $result['packages_found']);
        self::assertCount(1, $this->findSourceDeleteJobs($jobs, $organizationDto->id, $sourceDto->id));
        self::assertCount(1, $this->findPackageDeleteJobs($jobs, $organizationDto->id, $packageA));
    }

    #[Test]
    public function itDeletesSourceLessPackagesThroughPackageDeleteBeforeFinalizingOrganization(): void
    {
        $userDto         = $this->createVerifiedUser('owner-package-delete');
        $organizationDto = $this->createOrganization($userDto, 'delete-source-less-' . $this->suffix());
        $packageName     = 'vendor/source-less-' . $this->suffix();
        $this->createPackage($organizationDto, $packageName);
        $this->publishRelease($packageName, '1.0.0', $organizationDto->id);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/settings/delete',
            [
                'confirmationName' => $organizationDto->name,
            ],
        )->assertRedirect('/organizations');

        $deleteJobs    = $this->findOrganizationDeleteJobs($jobs, $organizationDto->id);
        $deletionRunId = (string) ($deleteJobs[0]->payload()['deletion_run_id'] ?? '');

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($deleteJobs[0]);

        self::assertSame([], $this->findSourceDeleteJobs($jobs, $organizationDto->id));
        $packageDeleteJobs = $this->findPackageDeleteJobs($jobs, $organizationDto->id, $packageName);
        self::assertCount(1, $packageDeleteJobs);
        self::assertSame(
            'organization-delete:' . $organizationDto->id . ':' . $deletionRunId . ':package:' . $packageName,
            $packageDeleteJobs[0]->idempotencyKey(),
        );
        self::assertSame($deletionRunId, $packageDeleteJobs[0]->payload()['deletion_run_id'] ?? null);
        self::assertSame('organization.delete', $packageDeleteJobs[0]->payload()['origin'] ?? null);

        $jobProcessor->process($packageDeleteJobs[0]);

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        self::assertNull($packages->findPackageInOrganization($organizationDto->id, $packageName));

        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        self::assertNull($releases->findRelease($packageName, '1.0.0', $organizationDto->id));
        self::assertSame([[$organizationDto->id, $packageName, $deletionRunId, 'organization.delete']], $this->fakeOrganizationArchiveStorageCleanupApiFacade->calls);

        $finalizeJobs = $this->findOrganizationFinalizeJobs($jobs, $organizationDto->id, $deletionRunId);
        self::assertCount(1, $finalizeJobs);
        $jobProcessor->process($finalizeJobs[0]);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertNull($organizations->findOrganization($organizationDto->id));
    }

    #[Test]
    public function itDoesNotFinalizeOrganizationWhenPackageArchiveCleanupFails(): void
    {
        $this->fakeOrganizationArchiveStorageCleanupApiFacade->shouldFail = true;

        $userDto         = $this->createVerifiedUser('owner-package-fail');
        $organizationDto = $this->createOrganization($userDto, 'delete-source-less-fail-' . $this->suffix());
        $packageName     = 'vendor/source-less-fail-' . $this->suffix();
        $this->createPackage($organizationDto, $packageName);
        $this->publishRelease($packageName, '1.0.0', $organizationDto->id);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/settings/delete',
            [
                'confirmationName' => $organizationDto->name,
            ],
        )->assertRedirect('/organizations');

        $deleteJobs    = $this->findOrganizationDeleteJobs($jobs, $organizationDto->id);
        $deletionRunId = (string) ($deleteJobs[0]->payload()['deletion_run_id'] ?? '');

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($deleteJobs[0]);

        $packageDeleteJobs = $this->findPackageDeleteJobs($jobs, $organizationDto->id, $packageName);
        self::assertCount(1, $packageDeleteJobs);
        $jobProcessor->process($packageDeleteJobs[0]);

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        self::assertNotNull($packages->findPackageInOrganization($organizationDto->id, $packageName));

        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        self::assertNotNull($releases->findRelease($packageName, '1.0.0', $organizationDto->id));
        self::assertSame(Job::STATUS_PENDING, $packageDeleteJobs[0]->status());

        $finalizeJobs = $this->findOrganizationFinalizeJobs($jobs, $organizationDto->id, $deletionRunId);
        self::assertCount(1, $finalizeJobs);
        $jobProcessor->process($finalizeJobs[0]);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations      = $this->getService(OrganizationApiFacadeInterface::class);
        $queuedOrganization = $organizations->findOrganization($organizationDto->id);
        self::assertNotNull($queuedOrganization);
        self::assertSame('deleting', $queuedOrganization->status);
        self::assertSame(Job::STATUS_PENDING, $finalizeJobs[0]->status());
    }

    #[Test]
    public function itRejectsWrongConfirmationWhenDeletingOrganization(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto, 'delete-org-wrong-' . $this->suffix());

        $this->logIn($userDto->email);
        $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/settings/delete',
            [
                'confirmationName' => 'wrong',
            ],
        )->assertRedirect('/organization/' . $organizationDto->slug . '/settings');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertNotNull($organizations->findOrganization($organizationDto->id));
    }

    #[Test]
    public function itRejectsNonOwnerDeletingOrganization(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        $this->logIn($member->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/settings/delete',
            [
                'confirmationName' => $organization->name,
            ],
        )->assertRedirect('/organization/' . $organization->slug);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertNotNull($organizations->findOrganization($organization->id));
    }

    private function createVerifiedUser(string $prefix): UserDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            $prefix . '-' . bin2hex(random_bytes(6)) . '@example.test',
            'Verified User',
            self::PASSWORD,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function createOrganization(UserDto $userDto, ?string $name = null): OrganizationDto
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        return $organizations->registerOrganization(
            $name ?? ('org-' . $this->suffix()),
            $userDto->id,
        );
    }

    /**
     * @return array{0: UserDto, 1: UserDto, 2: OrganizationDto}
     */
    private function createOrganizationWithOwnerAndMember(): array
    {
        $userDto         = $this->createVerifiedUser('owner');
        $organizationDto = $this->createOrganization($userDto, 'org-with-member-' . $this->suffix());
        $member          = $this->createVerifiedUser('member');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $organizations->addMember($organizationDto->id, $member->id, 'member');

        return [$userDto, $member, $organizationDto];
    }

    private function createInvitation(
        OrganizationDto $organizationDto,
        string $invitedByUserId,
        string $role = 'member'
    ): OrganizationInvitationDto {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        return $organizations->createInvitation($organizationDto->id, $invitedByUserId, $role);
    }

    private function findMemberIdByUser(OrganizationDto $organizationDto, string $userId): string
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $members       = $organizations->members($organizationDto->id);

        foreach ($members as $member) {
            if ($member->userId === $userId) {
                return $member->id;
            }
        }

        return '';
    }

    /**
     * @param list<array<string, mixed>> $memberItems
     *
     * @return array<string, mixed>
     */
    private function memberItemByEmail(array $memberItems, string $email): array
    {
        foreach ($memberItems as $memberItem) {
            if (($memberItem['email'] ?? null) === $email) {
                return $memberItem;
            }
        }

        self::fail('Member item was not found for email ' . $email);
    }

    private function createSource(OrganizationDto $organizationDto): SourceDto
    {
        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);

        return $sources->registerSource(new SourceRegistrationRequestDto(
            'generic_git',
            'https://git.example.test/acme/' . $this->suffix() . '.git',
            $organizationDto->id,
        ));
    }

    private function createPackage(
        OrganizationDto $organizationDto,
        string $packageName,
        ?SourceDto $sourceDto = null,
    ): void {
        /** @var PackageApiFacadeInterface $packages */
        $packages   = $this->getService(PackageApiFacadeInterface::class);
        $sourceType = 'github';
        $sourceUrl  = 'https://example.test/' . $packageName;
        $sourceId   = '';

        if ($sourceDto instanceof SourceDto) {
            $sourceType = $sourceDto->provider;
            $sourceUrl  = $sourceDto->url;
            $sourceId   = $sourceDto->id;
        }

        $packages->upsert(new PackageDto(
            name: $packageName,
            type: 'library',
            description: 'Package for organization deletion tests',
            visibility: 'public',
            sourceType: $sourceType,
            sourceUrl: $sourceUrl,
            createdAt: '2026-05-20T00:00:00+00:00',
            updatedAt: '2026-05-20T00:00:00+00:00',
            organizationId: $organizationDto->id,
            sourceId: $sourceId,
        ));
    }

    private function publishRelease(string $packageName, string $version, string $organizationId): void
    {
        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        $releases->publish(ReleaseBuilder::make([
            'packageName'    => $packageName,
            'version'        => $version,
            'reference'      => 'v' . $version,
            'organizationId' => $organizationId,
        ]));
    }

    private function logIn(string $email): void
    {
        $this->withCsrf()->post('/auth/login', [
            'email'    => $email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');
    }

    private function slugFromName(string $name): string
    {
        return strtolower(trim((string) preg_replace('/[^a-z0-9\-]+/i', '-', $name), '-'));
    }

    private function suffix(): string
    {
        return bin2hex(random_bytes(4));
    }

    /**
     * @return list<Job>
     */
    private function findOrganizationDeleteJobs(JobRepositoryInterface $jobRepository, string $organizationId): array
    {
        return $jobRepository->findByOrganization($organizationId, [
            'type' => 'organization.delete',
        ]);
    }

    /**
     * @return list<Job>
     */
    private function findOrganizationFinalizeJobs(
        JobRepositoryInterface $jobRepository,
        string $organizationId,
        string $deletionRunId,
    ): array {
        $found = [];
        foreach ($jobRepository->findByOrganization($organizationId, [
            'type' => 'organization.delete',
        ]) as $job) {
            if (
                ($job->payload()['stage'] ?? null) === 'finalize'
                && ($job->payload()['deletion_run_id'] ?? null) === $deletionRunId
            ) {
                $found[] = $job;
            }
        }

        return $found;
    }

    /**
     * @return list<Job>
     */
    private function findSourceDeleteJobs(
        JobRepositoryInterface $jobRepository,
        string $organizationId,
        ?string $sourceId = null,
    ): array {
        $found = [];
        foreach ($jobRepository->findByOrganization($organizationId, [
            'type' => 'source.delete',
        ]) as $job) {
            if (null !== $sourceId && ($job->payload()['source_id'] ?? null) !== $sourceId) {
                continue;
            }

            $found[] = $job;
        }

        return $found;
    }

    /**
     * @return list<Job>
     */
    private function findPackageDeleteJobs(
        JobRepositoryInterface $jobRepository,
        string $organizationId,
        string $packageName,
    ): array {
        $found = [];
        foreach ($jobRepository->findByOrganization($organizationId, [
            'type' => 'package.delete',
        ]) as $job) {
            if (($job->payload()['package_name'] ?? null) !== $packageName) {
                continue;
            }

            $found[] = $job;
        }

        return $found;
    }
}

final class FakeOrganizationArchiveStorageCleanupApiFacade implements ArchiveStorageCleanupApiFacadeInterface
{
    /** @var list<array{0: string, 1: string, 2: ?string, 3: string}> */
    public array $calls = [];

    public bool $shouldFail = false;

    public function schedulePackageArchiveCleanup(
        string $organizationId,
        string $packageName,
        ?string $deletionRunId = null,
        string $origin = '',
    ): array {
        $this->calls[] = [$organizationId, $packageName, $deletionRunId, $origin];

        if ($this->shouldFail) {
            throw new RuntimeException('Archive cleanup failed.');
        }

        return [
            'discovered' => 1,
            'scheduled'  => 1,
            'failed'     => 0,
        ];
    }
}
