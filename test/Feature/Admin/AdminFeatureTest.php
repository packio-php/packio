<?php

declare(strict_types=1);

namespace PackioTest\Feature\Admin;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Admin\Application\Settings\RuntimeSettingOverride;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Audit\Api\Dto\AuditEntryDto;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\IdentityCommandService;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Application\Service\SourceDeletionExecutionLeaseInterface;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_column;
use function array_filter;
use function array_first;
use function array_values;
use function bin2hex;
use function in_array;
use function is_array;
use function random_bytes;
use function rawurlencode;
use function str_replace;
use function str_starts_with;
use function ucfirst;

final class AdminFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function itRedirectsAnonymousUserFromAdminDashboard(): void
    {
        $this->get('/admin')->assertRedirect('/auth/login');
    }

    #[Test]
    public function itForbidsNonAdminFromAdminDashboard(): void
    {
        $userDto = $this->createUser('member');

        $this->logIn($userDto->email);

        $this->get('/admin')->assertStatus(403);
    }

    #[Test]
    public function itRendersAdminDashboardForAdmin(): void
    {
        $userDto = $this->createUser('admin', ['admin']);
        $this->createUser('regular');
        $this->createOrganization($userDto);
        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(200)
            ->assertInertiaComponent('Admin/Dashboard')
        ;

        $stats = $testResponse->json()['props']['stats'] ?? [];
        self::assertIsArray($stats);
        self::assertArrayHasKey('users_count', $stats);
        self::assertArrayHasKey('admins_count', $stats);
        self::assertArrayHasKey('organizations_count', $stats);
        self::assertArrayHasKey('packages_count', $stats);
        self::assertArrayHasKey('sources_count', $stats);
        self::assertGreaterThanOrEqual(1, (int) $stats['users_count']);
        self::assertGreaterThanOrEqual(1, (int) $stats['admins_count']);
        self::assertGreaterThanOrEqual(1, (int) $stats['organizations_count']);
    }

    #[Test]
    public function itDeniesAdminRouteAfterAdminRoleIsRemovedWithoutReLogin(): void
    {
        $userDto = $this->createUser('admin-demote', ['admin']);
        $this->logIn($userDto->email);

        $this->replaceRoles($userDto->id, ['user']);

        $this->get('/admin')->assertStatus(403);
    }

    #[Test]
    public function itAllowsAdminRouteAfterAdminRoleIsAddedWithoutReLogin(): void
    {
        $userDto = $this->createUser('admin-promote', ['user']);
        $this->logIn($userDto->email);

        $this->replaceRoles($userDto->id, ['admin']);

        $this->get('/admin', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->assertStatus(200);
    }

    #[Test]
    public function itRendersAdminUsersListForAdmin(): void
    {
        $userDto = $this->createUser('admin', ['admin']);
        $member  = $this->createUser('member');
        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin/users', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(200)
            ->assertInertiaComponent('Admin/Users')
        ;

        $users = $testResponse->json()['props']['users'] ?? [];
        self::assertIsArray($users);
        self::assertNotEmpty($users);

        $userIds = array_column($users, 'id');
        self::assertContains($userDto->id, $userIds);
        self::assertContains($member->id, $userIds);
    }

    #[Test]
    public function itLoadsUserDetailsForAdmin(): void
    {
        $userDto = $this->createUser('admin', ['admin']);
        $member  = $this->createUser('member');
        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin/users/' . $member->id, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(200)
            ->assertInertiaComponent('Admin/UserDetails')
        ;

        $record = $testResponse->json()['props']['user_record'] ?? [];
        self::assertSame($member->id, $record['id'] ?? null);
        self::assertSame($member->email, $record['email'] ?? null);
    }

    #[Test]
    public function itUpdatesUserByAdmin(): void
    {
        $userDto = $this->createUser('admin', ['admin']);
        $target  = $this->createUser('editable');
        $this->logIn($userDto->email);

        $newEmail = 'edited-' . bin2hex(random_bytes(6)) . '@example.test';

        $this->withCsrf()->post('/admin/users/' . $target->id, [
            'email'        => $newEmail,
            'display_name' => 'Edited User',
            'roles'        => ['user', 'admin'],
        ])->assertRedirect('/admin/users/' . $target->id);

        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $updated  = $identity->findUserById($target->id);
        self::assertNotNull($updated);
        self::assertSame($newEmail, $updated->email);
        self::assertSame('Edited User', $updated->displayName);
        self::assertSame(['user', 'admin'], $updated->roles);
    }

    #[Test]
    public function itRejectsRemovingAdminRoleFromTheLastActiveAdmin(): void
    {
        $this->demoteAndExpect($this->createUser('only-admin', ['admin']), 'error', 'The last active administrator cannot lose the admin role.', true);
    }

    #[Test]
    public function itAllowsRemovingAdminRoleWhenAnotherActiveAdminExists(): void
    {
        $userDto = $this->createUser('other-admin', ['admin']);

        $this->demoteAndExpect($this->createUser('demoted-admin', ['admin']), 'success', 'User updated.', false, $userDto);
    }

    #[Test]
    public function itIgnoresBannedAdminsWhenProtectingTheLastActiveAdmin(): void
    {
        $userDto = $this->createUser('active-admin', ['admin']);
        $banned  = $this->createUser('banned-admin', ['admin']);

        /** @var UserRepositoryInterface $users */
        $users  = $this->getService(UserRepositoryInterface::class);
        $entity = $users->findById($banned->id);
        self::assertInstanceOf(User::class, $entity);
        $entity->ban('Violation of project rules.', $userDto->id);
        $users->save($entity);

        $this->demoteAndExpect($userDto, 'error', 'The last active administrator cannot lose the admin role.', true);
        $this->demoteAndExpect($banned, 'success', 'User updated.', false, $userDto);
    }

    #[Test]
    public function itBansAndUnbansUserByAdminWithAnAuditHistory(): void
    {
        $userDto  = $this->createUser('ban-admin', ['admin']);
        $target   = $this->createUser('ban-target');
        $this->logIn($userDto->email);

        $this->withCsrf()->post('/admin/users/' . $target->id . '/ban', [
            'reason' => 'Violation of project rules.',
        ])->assertRedirect('/admin/users/' . $target->id);

        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $banned   = $identity->findUserById($target->id);
        self::assertNotNull($banned);
        self::assertTrue($banned->isBanned);
        self::assertSame('Violation of project rules.', $banned->banReason);

        /** @var AuditApiFacadeInterface $audit */
        $audit   = $this->getService(AuditApiFacadeInterface::class);
        $history = $audit->recentBySubject('user', $target->id);
        self::assertCount(1, $history);
        self::assertSame('identity.user.banned', $history[0]->event);
        self::assertSame($userDto->id, $history[0]->actorId);

        $this->withCsrf()->post('/admin/users/' . $target->id . '/unban', [
            'note' => 'Ban lifted by administrator.',
        ])->assertRedirect('/admin/users/' . $target->id);

        $unbanned = $identity->findUserById($target->id);
        self::assertNotNull($unbanned);
        self::assertFalse($unbanned->isBanned);

        $history = $audit->recentBySubject('user', $target->id);
        self::assertCount(2, $history);
        self::assertSame('identity.user.unbanned', $history[0]->event);
        self::assertSame('Ban lifted by administrator.', $history[0]->context['note'] ?? null);
    }

    #[Test]
    public function itReturnsJsonSuccessAndConflictForBanAndUnbanRequests(): void
    {
        $userDto = $this->createUser('ban-json-admin', ['admin']);
        $target  = $this->createUser('ban-json-target');
        $this->logIn($userDto->email);

        $headers = [
            'Accept' => 'application/json',
        ];

        $this->withCsrf()->post('/admin/users/' . $target->id . '/ban', [
            'reason' => 'Violation of project rules.',
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('message', 'User banned.')
        ;

        $this->withCsrf()->post('/admin/users/' . $target->id . '/ban', [
            'reason' => 'Second ban attempt.',
        ], $headers)
            ->assertStatus(409)
            ->assertJsonPath('message', 'User is already banned.')
        ;

        $this->withCsrf()->post('/admin/users/' . $target->id . '/unban', [
            'note' => 'Ban lifted by administrator.',
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('message', 'User unbanned.')
        ;

        $this->withCsrf()->post('/admin/users/' . $target->id . '/unban', [
            'note' => 'Second unban attempt.',
        ], $headers)
            ->assertStatus(409)
            ->assertJsonPath('message', 'User is not banned.')
        ;
    }

    #[Test]
    public function itRejectsInvalidAndConflictingUserBanRequests(): void
    {
        $userDto  = $this->createUser('ban-conflict-admin', ['admin']);
        $target   = $this->createUser('ban-conflict-target');
        $this->logIn($userDto->email);

        $this->withCsrf()->post('/admin/users/' . $target->id . '/ban', [
            'reason' => '',
        ])->assertRedirect('/admin/users/' . $target->id);

        /** @var IdentityApiFacadeInterface $identity */
        $identity              = $this->getService(IdentityApiFacadeInterface::class);
        $targetAfterInvalidBan = $identity->findUserById($target->id);
        self::assertNotNull($targetAfterInvalidBan);
        self::assertFalse($targetAfterInvalidBan->isBanned);

        $this->withCsrf()->post('/admin/users/' . $userDto->id . '/ban', [
            'reason' => 'Violation of project rules.',
        ])->assertRedirect('/admin/users/' . $userDto->id);
        $adminAfterSelfBan = $identity->findUserById($userDto->id);
        self::assertNotNull($adminAfterSelfBan);
        self::assertFalse($adminAfterSelfBan->isBanned);
    }

    #[Test]
    public function itRejectsWhitespaceOnlyModerationInputThroughMappedValidation(): void
    {
        $userDto  = $this->createUser('ban-whitespace-admin', ['admin']);
        $target   = $this->createUser('ban-whitespace-target');
        $this->logIn($userDto->email);

        $this->withCsrf()->post(
            '/admin/users/' . $target->id . '/ban',
            [
                'reason' => '   ',
            ],
            [
                'Accept' => 'application/json',
            ],
        )
            ->assertStatus(422)
            ->assertJsonPath('error', 'Invalid ban reason.')
        ;

        /** @var IdentityApiFacadeInterface $identity */
        $identity                 = $this->getService(IdentityApiFacadeInterface::class);
        $targetAfterWhitespaceBan = $identity->findUserById($target->id);
        self::assertNotNull($targetAfterWhitespaceBan);
        self::assertFalse($targetAfterWhitespaceBan->isBanned);

        $this->withCsrf()->post('/admin/users/' . $target->id . '/ban', [
            'reason' => 'Violation of project rules.',
        ])->assertRedirect('/admin/users/' . $target->id);

        $this->withCsrf()->post('/admin/users/' . $target->id . '/unban', [
            'note' => "\t \n",
        ])->assertRedirect('/admin/users/' . $target->id);

        $targetAfterWhitespaceUnban = $identity->findUserById($target->id);
        self::assertNotNull($targetAfterWhitespaceUnban);
        self::assertTrue($targetAfterWhitespaceUnban->isBanned);
    }

    #[Test]
    public function itRendersAdminOrganizationsListForAdmin(): void
    {
        $userDto         = $this->createUser('admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto);
        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin/organizations', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(200)
            ->assertInertiaComponent('Admin/Organizations')
        ;

        $organizations = $testResponse->json()['props']['organizations'] ?? [];
        self::assertIsArray($organizations);
        self::assertContains($organizationDto->id, array_column($organizations, 'id'));
    }

    #[Test]
    public function itPaginatesAdminOrganizations(): void
    {
        $userDto = $this->createUser('admin', ['admin']);
        $this->createOrganization($userDto);
        $this->createOrganization($userDto);
        $this->createOrganization($userDto);
        $this->logIn($userDto->email);

        $props = $this->get('/admin/organizations?per_page=2&page=2', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->json()['props'] ?? [];
        self::assertCount(1, $props['organizations'] ?? []);
        self::assertSame(3, $props['pagination']['total'] ?? null);
        self::assertSame(2, $props['pagination']['current_page'] ?? null);
        self::assertArrayNotHasKey('items', $props['pagination']);

        $props = $this->get('/admin/organizations?per_page=2&page=999', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->json()['props'] ?? [];
        self::assertSame(2, $props['pagination']['current_page'] ?? null);
        self::assertCount(1, $props['organizations'] ?? []);
    }

    #[Test]
    public function itCreatesRevokesAndDeletesInvitationsAsAdmin(): void
    {
        $userDto         = $this->createUser('admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto);
        $this->logIn($userDto->email);
        $base = '/admin/organizations/' . $organizationDto->id;

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        $this->withCsrf()->post($base . '/invitations', [
            'role' => 'member',
        ])->assertRedirect($base);
        self::assertSame('Invitation created.', $this->adminOrganizationFlash($base, 'success'));

        $invitations = $organizations->invitations($organizationDto->id);
        self::assertCount(1, $invitations);
        $invitationId = $invitations[0]->id;

        $this->withCsrf()->post($base . '/invitations/' . $invitationId . '/revoke')->assertRedirect($base);
        self::assertSame('Invitation revoked.', $this->adminOrganizationFlash($base, 'success'));

        $this->withCsrf()->post($base . '/invitations/' . $invitationId . '/delete')->assertRedirect($base);
        self::assertSame('Invitation deleted.', $this->adminOrganizationFlash($base, 'success'));
        self::assertCount(0, $organizations->invitations($organizationDto->id));

        $this->withCsrf()->post($base . '/invitations/00000000-0000-4000-8000-000000000000/delete')->assertRedirect($base);
        self::assertSame('Only non-pending invitations can be deleted.', $this->adminOrganizationFlash($base, 'error'));
    }

    #[Test]
    public function itRedirectsAnonymousUserFromAdminJobs(): void
    {
        $this->get('/admin/jobs')->assertRedirect('/auth/login');
        $this->get('/admin/packages')->assertRedirect('/auth/login');
        $this->get('/admin/sources')->assertRedirect('/auth/login');
        $this->get('/admin/sources/some-source-id/deletion-preview')->assertRedirect('/auth/login');
        $this->withCsrf()->post('/admin/sources/some-source-id/delete', [
            'confirmationName' => 'some-source',
        ])->assertRedirect('/auth/login');
        $this->withCsrf()->post('/admin/organizations/some-org-id/delete', [
            'confirmationName' => 'some-org',
        ])->assertRedirect('/auth/login');
    }

    #[Test]
    public function itForbidsNonAdminFromAdminJobs(): void
    {
        $userDto = $this->createUser('jobs-member');
        $this->logIn($userDto->email);

        $this->get('/admin/jobs')->assertStatus(403);
        $this->get('/admin/jobs?organization_id=other-org&deletion_run_id=other-run&worker_id=worker-a')->assertStatus(403);
        $this->get('/admin/jobs/some-job-id')->assertStatus(403);
        $this->withCsrf()->post('/admin/jobs/some-job-id/restart')->assertStatus(403);
        $this->get('/admin/packages')->assertStatus(403);
        $this->get('/admin/sources')->assertStatus(403);
        $this->withCsrf()->post('/admin/sources/some-source-id/requeue-delete')->assertStatus(403);
        $this->withCsrf()->post('/admin/sources/some-source-id/force-requeue-delete')->assertStatus(403);
        $this->withCsrf()->post('/admin/sources/some-source-id/cancel-delete')->assertStatus(403);
        $this->withCsrf()->post('/admin/sources/some-source-id/force-finalize-delete')->assertStatus(403);
        $this->withCsrf()->post('/admin/sources/some-source-id/delete', [
            'confirmationName' => 'some-source',
        ])->assertStatus(403);
        $this->get('/admin/sources/some-source-id/deletion-preview')->assertStatus(403);
        $this->withCsrf()->post('/admin/organizations/some-org-id/delete', [
            'confirmationName' => 'some-org',
        ])->assertStatus(403);
    }

    #[Test]
    public function itRendersAndFiltersAdminPackagesAcrossOrganizations(): void
    {
        $userDto         = $this->createUser('packages-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'packages-first-' . bin2hex(random_bytes(6)));
        $secondOrg       = $this->createOrganization($userDto, 'packages-second-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'admin-package-source', 'github');
        $hiddenSource    = $this->createSource($secondOrg, 'admin-package-hidden-source', 'github');

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        $sources->markSyncFailed($sourceDto->id, 'Admin package filter failure.');

        $managedName = 'admin/managed-' . bin2hex(random_bytes(4));
        $hiddenName  = 'admin/hidden-' . bin2hex(random_bytes(4));
        $this->createPackage($organizationDto, $managedName, sourceId: $sourceDto->id, sourceUrl: $sourceDto->url);
        $this->createPackage($secondOrg, $hiddenName, visibility: 'private', sourceId: $hiddenSource->id, sourceUrl: $hiddenSource->url);

        /** @var PackageApiFacadeInterface $packagesFacade */
        $packagesFacade = $this->getService(PackageApiFacadeInterface::class);
        $packagesFacade->updateActivityBySourceIdInContext(new ActorContext($secondOrg->id, $userDto->id, 'owner'), $hiddenSource->id, false);

        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin/packages', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(200)
            ->assertInertiaComponent('Admin/Packages')
        ;

        $packages = $testResponse->json()['props']['packages'] ?? [];
        self::assertIsArray($packages);
        self::assertContains($managedName, array_column($packages, 'name'));
        self::assertContains($hiddenName, array_column($packages, 'name'));
        self::assertGreaterThanOrEqual(2, $testResponse->json()['props']['package_stats']['total'] ?? 0);

        $managedResponse = $this->get('/admin/packages?source=managed&source_sync_status=failed', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertContains($managedName, array_column($managedResponse->json()['props']['packages'] ?? [], 'name'));
        self::assertNotContains($hiddenName, array_column($managedResponse->json()['props']['packages'] ?? [], 'name'));

        $hiddenResponse = $this->get('/admin/packages?organization_id=' . $secondOrg->id . '&listed=hidden&visibility=private', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame([$hiddenName], array_column($hiddenResponse->json()['props']['packages'] ?? [], 'name'));

        $sortResponse = $this->get('/admin/packages?search=admin/&sort=name&direction=desc', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame([$managedName, $hiddenName], array_column($sortResponse->json()['props']['packages'] ?? [], 'name'));
        self::assertSame('name', $sortResponse->json()['props']['filters']['sort'] ?? null);
        self::assertSame('desc', $sortResponse->json()['props']['filters']['direction'] ?? null);
    }

    #[Test]
    public function itRendersAndFiltersAdminSourcesAcrossOrganizations(): void
    {
        $userDto         = $this->createUser('sources-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'sources-first-' . bin2hex(random_bytes(6)));
        $secondOrg       = $this->createOrganization($userDto, 'sources-second-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'admin-source-github', 'github');
        $genericSource   = $this->createSource($secondOrg, 'admin-source-generic', 'generic_git');

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        $sources->markSyncFailed($sourceDto->id, 'Admin source filter failure.');

        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin/sources', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(200)
            ->assertInertiaComponent('Admin/Sources')
        ;

        $sourceIds = array_column($testResponse->json()['props']['sources'] ?? [], 'id');
        self::assertContains($sourceDto->id, $sourceIds);
        self::assertContains($genericSource->id, $sourceIds);
        self::assertGreaterThanOrEqual(2, $testResponse->json()['props']['source_stats']['total'] ?? 0);

        $githubResponse = $this->get('/admin/sources?provider=github&sync_status=failed', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertContains($sourceDto->id, array_column($githubResponse->json()['props']['sources'] ?? [], 'id'));
        self::assertNotContains($genericSource->id, array_column($githubResponse->json()['props']['sources'] ?? [], 'id'));

        $genericResponse = $this->get('/admin/sources?organization_id=' . $secondOrg->id . '&search=admin-source-generic', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame([$genericSource->id], array_column($genericResponse->json()['props']['sources'] ?? [], 'id'));

        $sortResponse = $this->get('/admin/sources?search=admin-source&sort=provider&direction=asc', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame([$genericSource->id, $sourceDto->id], array_column($sortResponse->json()['props']['sources'] ?? [], 'id'));
        self::assertSame('provider', $sortResponse->json()['props']['filters']['sort'] ?? null);
        self::assertSame('asc', $sortResponse->json()['props']['filters']['direction'] ?? null);
    }

    #[Test]
    public function itShowsSourceDeleteRecoverySummaryForDeletingSourceWithoutRetainedJob(): void
    {
        $userDto         = $this->createUser('source-recovery-summary-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-recovery-summary-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'source-recovery-summary', 'github');
        $packageName     = 'admin/recovery-summary-' . bin2hex(random_bytes(4));
        $this->createPackage($organizationDto, $packageName, sourceId: $sourceDto->id, sourceUrl: $sourceDto->url);
        $this->markSourceDeletingWithoutRetainedJob($sourceDto);

        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin/sources?delete_health=stale_without_job', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(200)
            ->assertInertiaComponent('Admin/Sources')
        ;

        $sourceRow = $this->sourceRow($testResponse->json()['props']['sources'] ?? [], $sourceDto->id);
        self::assertSame('deleting', $sourceRow['logical_status'] ?? null);
        self::assertSame('stale_without_job', $sourceRow['recovery']['health'] ?? null);
        self::assertSame(0, $sourceRow['recovery']['related_jobs_count'] ?? null);
        self::assertSame(1, $sourceRow['recovery']['linked_packages_count'] ?? null);
        self::assertSame(0, $sourceRow['recovery']['linked_releases_count'] ?? null);
        self::assertSame(1, $testResponse->json()['props']['source_stats']['deleting_missing_jobs'] ?? null);
        self::assertSame(['stale_without_job'], $testResponse->json()['props']['filters']['delete_health'] ?? null);
    }

    #[Test]
    public function itRequeuesDeletingSourceWithoutRetainedJobAndAuditsTheAction(): void
    {
        $userDto         = $this->createUser('source-requeue-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-requeue-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'source-requeue', 'github');
        $this->markSourceDeletingWithoutRetainedJob($sourceDto);
        $this->logIn($userDto->email);

        $this->withCsrf()
            ->post('/admin/sources/' . $sourceDto->id . '/requeue-delete?return_to=' . rawurlencode('/admin/sources?delete_health=stale_without_job'))
            ->assertRedirect('/admin/sources?delete_health=stale_without_job')
        ;

        /** @var JobRepositoryInterface $jobs */
        $jobs       = $this->getService(JobRepositoryInterface::class);
        $deleteJobs = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);

        self::assertCount(1, $deleteJobs);
        self::assertSame('source.delete', $deleteJobs[0]->type());
        self::assertStringStartsWith('source-delete-recovery:' . $sourceDto->id . ':', (string) $deleteJobs[0]->idempotencyKey());
        self::assertSame($sourceDto->id, $deleteJobs[0]->payload()['source_id'] ?? null);
        self::assertNotSame('', $deleteJobs[0]->payload()['deletion_run_id'] ?? '');

        $auditEntryDto = $this->latestSourceAuditEntry($organizationDto->id, $sourceDto->id, 'source.delete.requeued_by_admin');
        self::assertSame($userDto->id, $auditEntryDto->context['admin_user_id'] ?? null);
        self::assertSame($deleteJobs[0]->getIdentifier()->toString(), $auditEntryDto->context['new_job_id'] ?? null);
        self::assertTrue($auditEntryDto->context['job_created'] ?? null);
        self::assertSame('stale_without_job', $auditEntryDto->context['previous_recovery_health'] ?? null);

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($deleteJobs[0]);

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        self::assertNull($sources->findSource($sourceDto->id));
    }

    #[Test]
    public function itCreatesANewRecoveryGenerationAfterATerminalSourceRecoveryJob(): void
    {
        $userDto         = $this->createUser('source-requeue-terminal-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-requeue-terminal-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'source-requeue-terminal', 'github');
        $this->markSourceDeletingWithoutRetainedJob($sourceDto);
        $this->logIn($userDto->email);

        $this->withCsrf()
            ->post('/admin/sources/' . $sourceDto->id . '/requeue-delete')
            ->assertRedirect('/admin/sources')
        ;

        /** @var JobRepositoryInterface $jobs */
        $jobs       = $this->getService(JobRepositoryInterface::class);
        $firstJob   = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id)[0];
        $firstRunId = (string) ($firstJob->payload()['deletion_run_id'] ?? '');
        $firstJob->markRunning('worker-source-recovery');
        $firstJob->markFailed('recovery failed');
        $jobs->save($firstJob);

        $this->withCsrf()
            ->post('/admin/sources/' . $sourceDto->id . '/requeue-delete')
            ->assertRedirect('/admin/sources')
        ;

        $deleteJobs = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
        self::assertCount(2, $deleteJobs);
        $secondJob = array_first(array_filter(
            $deleteJobs,
            static fn (Job $job): bool => $job->getIdentifier()->toString() !== $firstJob->getIdentifier()->toString(),
        ));
        self::assertInstanceOf(Job::class, $secondJob);
        self::assertSame(Job::STATUS_PENDING, $secondJob->status());
        self::assertNotSame($firstRunId, $secondJob->payload()['deletion_run_id'] ?? null);

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($secondJob);

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        self::assertNull($sources->findSource($sourceDto->id));
    }

    #[Test]
    public function itReturnsExistingActiveDeleteJobWhenAdminRequeuesSourceDeletion(): void
    {
        $userDto         = $this->createUser('source-requeue-existing-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-requeue-existing-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'source-requeue-existing', 'github');

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        self::assertTrue($sources->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        /** @var JobRepositoryInterface $jobs */
        $jobs       = $this->getService(JobRepositoryInterface::class);
        $deleteJobs = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
        self::assertCount(1, $deleteJobs);

        $this->logIn($userDto->email);
        $this->withCsrf()
            ->post('/admin/sources/' . $sourceDto->id . '/requeue-delete')
            ->assertRedirect('/admin/sources')
        ;

        $deleteJobsAfterRequeue = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
        self::assertCount(1, $deleteJobsAfterRequeue);
        self::assertSame($deleteJobs[0]->getIdentifier()->toString(), $deleteJobsAfterRequeue[0]->getIdentifier()->toString());

        $auditEntryDto = $this->latestSourceAuditEntry($organizationDto->id, $sourceDto->id, 'source.delete.requeued_by_admin');
        self::assertFalse($auditEntryDto->context['job_created'] ?? null);
        self::assertSame('in_progress', $auditEntryDto->context['previous_recovery_health'] ?? null);
    }

    #[Test]
    public function itForceRequeuesSourceDeletionEvenWhenNormalDeleteJobExists(): void
    {
        $userDto         = $this->createUser('source-force-requeue-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-force-requeue-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'source-force-requeue', 'github');

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        self::assertTrue($sources->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        $this->logIn($userDto->email);
        $this->withCsrf()
            ->post('/admin/sources/' . $sourceDto->id . '/force-requeue-delete')
            ->assertRedirect('/admin/sources')
        ;

        /** @var JobRepositoryInterface $jobs */
        $jobs       = $this->getService(JobRepositoryInterface::class);
        $deleteJobs = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
        self::assertCount(2, $deleteJobs);

        $forceJobs = array_values(array_filter(
            $deleteJobs,
            static fn (Job $job): bool => str_starts_with((string) $job->idempotencyKey(), 'source-delete-recovery:' . $sourceDto->id . ':'),
        ));
        self::assertCount(1, $forceJobs);
        self::assertSame('admin-source-delete-recovery', $forceJobs[0]->payload()['origin'] ?? null);
        self::assertNotSame('', $forceJobs[0]->payload()['deletion_run_id'] ?? '');

        $auditEntryDto = $this->latestSourceAuditEntry($organizationDto->id, $sourceDto->id, 'source.delete.force_requeued_by_admin');
        self::assertSame($forceJobs[0]->getIdentifier()->toString(), $auditEntryDto->context['new_job_id'] ?? null);
        self::assertSame($forceJobs[0]->payload()['deletion_run_id'] ?? null, $auditEntryDto->context['deletion_run_id'] ?? null);

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($forceJobs[0]);
        self::assertNull($sources->findSource($sourceDto->id));
    }

    #[Test]
    public function itRejectsForceRequeueWhileSourceDeleteJobHasAnExecutionLease(): void
    {
        $userDto         = $this->createUser('source-force-requeue-running-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-force-requeue-running-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'source-force-requeue-running', 'github');

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        self::assertTrue($sources->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        /** @var JobRepositoryInterface $jobs */
        $jobs      = $this->getService(JobRepositoryInterface::class);
        $deleteJob = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id)[0];
        $deleteJob->markRunning('worker-source-delete');
        $jobs->save($deleteJob);

        /** @var SourceDeletionExecutionLeaseInterface $executionLease */
        $executionLease = $this->getService(SourceDeletionExecutionLeaseInterface::class);
        $deletionRunId  = (string) ($deleteJob->payload()['deletion_run_id'] ?? '');
        self::assertTrue($executionLease->acquire(
            $sourceDto->id,
            $organizationDto->id,
            $deletionRunId,
        ));

        $this->logIn($userDto->email);
        $this->withCsrf()
            ->post('/admin/sources/' . $sourceDto->id . '/force-requeue-delete')
            ->assertRedirect('/admin/sources')
        ;

        self::assertCount(1, $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id));
        self::assertSame('running', $jobs->findById($deleteJob->getIdentifier()->toString())?->status());
        $executionLease->rollback($sourceDto->id, $organizationDto->id, $deletionRunId);
    }

    #[Test]
    public function itCancelsDeletingSourceAsInactiveWhenDeleteCleanupHasNotStarted(): void
    {
        $userDto         = $this->createUser('source-cancel-delete-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-cancel-delete-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'source-cancel-delete', 'github');
        $this->markSourceDeletingWithoutRetainedJob($sourceDto);
        $this->logIn($userDto->email);

        $this->withCsrf()->post('/admin/sources/' . $sourceDto->id . '/cancel-delete', [
            'confirmation' => 'CANCEL DELETE',
            'cancel_jobs'  => '0',
        ])->assertRedirect('/admin/sources');

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        $updated = $sources->findSource($sourceDto->id);
        self::assertNotNull($updated);
        self::assertSame('active', $updated->status);
        self::assertFalse($updated->active);

        $auditEntryDto = $this->latestSourceAuditEntry($organizationDto->id, $sourceDto->id, 'source.delete.canceled_by_admin');
        self::assertSame('stale_without_job', $auditEntryDto->context['previous_recovery_health'] ?? null);
        self::assertFalse($auditEntryDto->context['cancel_jobs_requested'] ?? null);
    }

    #[Test]
    public function itShowsTheRecoveryRejectionReason(): void
    {
        $userDto         = $this->createUser('source-cancel-reason-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-cancel-reason-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'source-cancel-reason', 'github');
        $this->markSourceDeletingWithoutRetainedJob($sourceDto);
        $this->logIn($userDto->email);

        $this->withCsrf()->post('/admin/sources/' . $sourceDto->id . '/cancel-delete', [
            'confirmation' => 'wrong',
        ])->assertRedirect('/admin/sources');

        $testResponse = $this->get('/admin/sources', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame('Type CANCEL DELETE to confirm cancellation.', $testResponse->json()['flash']['error'] ?? null);
    }

    #[Test]
    public function itForceFinalizesDeletingSourceWithRemainingPackageMetadata(): void
    {
        $userDto         = $this->createUser('source-force-finalize-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-force-finalize-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'source-force-finalize', 'github');
        $packageName     = 'admin/force-finalize-' . bin2hex(random_bytes(4));
        $this->createPackage($organizationDto, $packageName, sourceId: $sourceDto->id, sourceUrl: $sourceDto->url);
        $this->markSourceDeletingWithoutRetainedJob($sourceDto);
        $this->logIn($userDto->email);

        $this->withCsrf()->post('/admin/sources/' . $sourceDto->id . '/force-finalize-delete', [
            'confirmation'                  => 'FORCE DELETE SOURCE',
            'acknowledge_orphaned_archives' => '1',
        ])->assertRedirect('/admin/sources');

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        self::assertNull($sources->findSource($sourceDto->id));

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        self::assertNull($packages->findPackageInOrganization($organizationDto->id, $packageName));

        $auditEntryDto = $this->latestSourceAuditEntry($organizationDto->id, $sourceDto->id, 'source.delete.force_finalized_by_admin');
        self::assertSame('high', $auditEntryDto->context['severity'] ?? null);
        self::assertSame(1, $auditEntryDto->context['packages_deleted'] ?? null);
        self::assertSame(0, $auditEntryDto->context['archive_cleanup_failed'] ?? null);
        self::assertTrue($auditEntryDto->context['acknowledge_orphaned_archives'] ?? null);
    }

    #[Test]
    public function itInitiatesSourceDeletionWithTypedConfirmationAndAuditsTheAction(): void
    {
        $userDto         = $this->createUser('source-delete-initiate-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-delete-initiate-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'admin-source-delete-initiate', 'github');

        /** @var SourceApiFacadeInterface $sources */
        $sources       = $this->getService(SourceApiFacadeInterface::class);
        $fetchedSource = $sources->findSource($sourceDto->id);
        self::assertNotNull($fetchedSource);
        $confirmationName = '' !== $fetchedSource->displayName
            ? $fetchedSource->displayName
            : $fetchedSource->owner . '/' . $fetchedSource->repository;

        $this->logIn($userDto->email);

        $this->withCsrf()->post('/admin/sources/' . $sourceDto->id . '/delete', [
            'confirmationName' => $confirmationName,
        ])->assertRedirect('/admin/sources');

        $updated = $sources->findSource($sourceDto->id);
        self::assertNotNull($updated);
        self::assertSame('deleting', $updated->status);

        /** @var JobRepositoryInterface $jobs */
        $jobs       = $this->getService(JobRepositoryInterface::class);
        $deleteJobs = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
        self::assertCount(1, $deleteJobs);
        self::assertSame('source.delete', $deleteJobs[0]->type());
        self::assertStringStartsWith('source-delete:' . $sourceDto->id . ':', (string) $deleteJobs[0]->idempotencyKey());
        self::assertSame($sourceDto->id, $deleteJobs[0]->payload()['source_id'] ?? null);
        self::assertSame('admin-source-delete-initiation', $deleteJobs[0]->payload()['origin'] ?? null);

        $auditEntryDto = $this->latestSourceAuditEntry($organizationDto->id, $sourceDto->id, 'source.delete.initiated_by_admin');
        self::assertSame($userDto->id, $auditEntryDto->context['admin_user_id'] ?? null);
        self::assertSame($sourceDto->id, $auditEntryDto->context['source_id'] ?? null);
        self::assertSame($deleteJobs[0]->idempotencyKey(), $auditEntryDto->context['job_idempotency_key'] ?? null);
        self::assertSame('admin-source-delete-initiation', $auditEntryDto->context['action_origin'] ?? null);
    }

    #[Test]
    public function itRejectsMismatchedSourceDeletionConfirmationWithoutDeletionOrAudit(): void
    {
        $userDto         = $this->createUser('source-delete-mismatch-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-delete-mismatch-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'admin-source-delete-mismatch', 'github');

        $this->logIn($userDto->email);

        $this->withCsrf()->post('/admin/sources/' . $sourceDto->id . '/delete', [
            'confirmationName' => 'not-the-source-name',
        ])->assertRedirect('/admin/sources');

        $testResponse = $this->get('/admin/sources', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame(
            'Type the exact source name to confirm deletion.',
            $testResponse->json()['flash']['error'] ?? null,
        );

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        self::assertSame('active', $sources->findSource($sourceDto->id)?->status);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertEmpty($jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id));

        /** @var AuditApiFacadeInterface $audit */
        $audit = $this->getService(AuditApiFacadeInterface::class);
        foreach ($audit->recentBySource($organizationDto->id, $sourceDto->id, 20) as $auditEntryDto) {
            self::assertNotSame('source.delete.initiated_by_admin', $auditEntryDto->event);
        }
    }

    #[Test]
    public function itIdempotentlyRetriesSourceDeletionInitiationWithoutDuplicateJobOrAudit(): void
    {
        $userDto         = $this->createUser('source-delete-repeat-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-delete-repeat-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'admin-source-delete-repeat', 'github');

        /** @var SourceApiFacadeInterface $sources */
        $sources       = $this->getService(SourceApiFacadeInterface::class);
        $fetchedSource = $sources->findSource($sourceDto->id);
        self::assertNotNull($fetchedSource);
        $confirmationName = '' !== $fetchedSource->displayName
            ? $fetchedSource->displayName
            : $fetchedSource->owner . '/' . $fetchedSource->repository;

        $this->logIn($userDto->email);

        $this->withCsrf()->post('/admin/sources/' . $sourceDto->id . '/delete', [
            'confirmationName' => $confirmationName,
        ])->assertRedirect('/admin/sources');

        $this->withCsrf()->post('/admin/sources/' . $sourceDto->id . '/delete', [
            'confirmationName' => $confirmationName,
        ])->assertRedirect('/admin/sources');

        $updated = $sources->findSource($sourceDto->id);
        self::assertNotNull($updated);
        self::assertSame('deleting', $updated->status);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertCount(1, $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id));

        /** @var AuditApiFacadeInterface $audit */
        $audit             = $this->getService(AuditApiFacadeInterface::class);
        $initiationEntries = array_filter(
            $audit->recentBySource($organizationDto->id, $sourceDto->id, 20),
            static fn (AuditEntryDto $auditEntryDto): bool => 'source.delete.initiated_by_admin' === $auditEntryDto->event,
        );
        self::assertCount(1, $initiationEntries);
    }

    #[Test]
    public function itReArmsSourceDeletionAfterACanceledDeletionLifecycle(): void
    {
        $userDto         = $this->createUser('source-delete-rearm-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-delete-rearm-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'admin-source-delete-rearm', 'github');

        /** @var SourceApiFacadeInterface $sources */
        $sources       = $this->getService(SourceApiFacadeInterface::class);
        $fetchedSource = $sources->findSource($sourceDto->id);
        self::assertNotNull($fetchedSource);
        $confirmationName = '' !== $fetchedSource->displayName
            ? $fetchedSource->displayName
            : $fetchedSource->owner . '/' . $fetchedSource->repository;

        $this->logIn($userDto->email);

        // First initiation: pending cleanup job.
        $this->withCsrf()->post('/admin/sources/' . $sourceDto->id . '/delete', [
            'confirmationName' => $confirmationName,
        ])->assertRedirect('/admin/sources');

        // Cancel the pending deletion lifecycle: the job becomes canceled and
        // the source is restored inactive.
        $this->withCsrf()->post('/admin/sources/' . $sourceDto->id . '/cancel-delete', [
            'confirmation' => 'CANCEL DELETE',
            'cancel_jobs'  => '1',
        ])->assertRedirect('/admin/sources');

        /** @var JobRepositoryInterface $jobs */
        $jobs         = $this->getService(JobRepositoryInterface::class);
        $canceledJobs = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
        self::assertCount(1, $canceledJobs);
        self::assertSame('canceled', $canceledJobs[0]->status());

        // The second initiation must schedule a fresh runnable job instead of
        // reusing the consumed idempotency key.
        $this->withCsrf()->post('/admin/sources/' . $sourceDto->id . '/delete', [
            'confirmationName' => $confirmationName,
        ])->assertRedirect('/admin/sources');

        $updated = $sources->findSource($sourceDto->id);
        self::assertNotNull($updated);
        self::assertSame('deleting', $updated->status);

        $jobsAfterRearm = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
        self::assertCount(2, $jobsAfterRearm);

        $pendingJobs = array_values(array_filter(
            $jobsAfterRearm,
            static fn (Job $job): bool => 'pending' === $job->status(),
        ));
        self::assertCount(1, $pendingJobs);
        self::assertNotSame($canceledJobs[0]->getIdentifier()->toString(), $pendingJobs[0]->getIdentifier()->toString());

        /** @var AuditApiFacadeInterface $audit */
        $audit             = $this->getService(AuditApiFacadeInterface::class);
        $initiationEntries = array_filter(
            $audit->recentBySource($organizationDto->id, $sourceDto->id, 20),
            static fn (AuditEntryDto $auditEntryDto): bool => 'source.delete.initiated_by_admin' === $auditEntryDto->event,
        );
        self::assertCount(2, $initiationEntries);
    }

    #[Test]
    public function itReturnsNotFoundForMissingSourceDeletionTargets(): void
    {
        $userDto = $this->createUser('source-delete-missing-admin', ['admin']);
        $this->logIn($userDto->email);

        $this->get('/admin/sources/not-a-source/deletion-preview', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->assertStatus(404)->assertJsonPath('error', 'Source not found.');

        $this->withCsrf()->post('/admin/sources/not-a-source/delete', [
            'confirmationName' => 'not-a-source',
        ])->assertRedirect('/admin/sources');

        $testResponse = $this->get('/admin/sources', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame('Source not found.', $testResponse->json()['flash']['error'] ?? null);
    }

    #[Test]
    public function itReturnsASafeDeletionPreviewForASourceWithPackages(): void
    {
        $userDto         = $this->createUser('source-delete-preview-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'source-delete-preview-' . bin2hex(random_bytes(6)));
        $sourceDto       = $this->createSource($organizationDto, 'admin-source-delete-preview', 'github');
        $this->createPackage(
            $organizationDto,
            'admin/source-delete-preview-' . bin2hex(random_bytes(4)),
            sourceId: $sourceDto->id,
            sourceUrl: $sourceDto->url,
        );

        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin/sources/' . $sourceDto->id . '/deletion-preview', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200);
        $payload = $testResponse->json();
        self::assertSame($sourceDto->id, $payload['source']['id'] ?? null);
        self::assertSame($organizationDto->id, $payload['source']['organization_id'] ?? null);
        self::assertSame(1, $payload['packages_count'] ?? null);
        self::assertSame(0, $payload['releases_count'] ?? null);
        self::assertArrayNotHasKey('provider_config', $payload['source']);
        self::assertArrayNotHasKey('token', $payload['source']);
        self::assertArrayNotHasKey('configuration', $payload['source']);
    }

    #[Test]
    public function itRendersFiltersSortsAndShowsAdminJobsAcrossOrganizations(): void
    {
        $userDto         = $this->createUser('jobs-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'jobs-first-' . bin2hex(random_bytes(6)));
        $secondOrg       = $this->createOrganization($userDto, 'jobs-second-' . bin2hex(random_bytes(6)));

        /** @var JobRepositoryInterface $jobRepo */
        $jobRepo = $this->getService(JobRepositoryInterface::class);

        /** @var JobLogRepositoryInterface $jobLogRepo */
        $jobLogRepo = $this->getService(JobLogRepositoryInterface::class);
        $failedJob  = Job::schedule(
            organizationId: $organizationDto->id,
            type: 'source.delete',
            payload: [
                'source_id'       => 'source-1',
                'deletion_run_id' => 'run-admin-jobs',
            ],
            priority: 1,
            maxAttempts: 2,
            idempotencyKey: 'source-delete:source-1',
        );
        $failedJob->markRunning('worker-a');
        $failedJob->markFailed('delete failed', 'details');
        $jobRepo->save($failedJob);
        $jobLogRepo->save(JobLog::record($failedJob->getIdentifier()->toString(), JobLog::LEVEL_WARNING, 'Cleanup warning'));

        $completedJob = Job::schedule(
            organizationId: $secondOrg->id,
            type: 'package.delete',
            payload: [
                'package_name' => 'vendor/package',
            ],
            priority: 9,
        );
        $completedJob->markRunning('worker-b');
        $completedJob->markCompleted();
        $jobRepo->save($completedJob);

        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin/jobs', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(200)
            ->assertInertiaComponent('Admin/Jobs')
        ;

        $jobs = $testResponse->json()['props']['jobs'] ?? [];
        self::assertIsArray($jobs);
        self::assertContains($failedJob->getIdentifier()->toString(), array_column($jobs, 'id'));
        self::assertContains($completedJob->getIdentifier()->toString(), array_column($jobs, 'id'));
        self::assertSame(1, $testResponse->json()['props']['status_counts']['failed'] ?? null);
        self::assertSame(1, $testResponse->json()['props']['status_counts']['completed'] ?? null);

        self::assertSame(1, $this->jobWarningCount($jobs, $failedJob->getIdentifier()->toString()));
        self::assertSame(0, $this->jobWarningCount($jobs, $completedJob->getIdentifier()->toString()));

        $statusResponse = $this->get('/admin/jobs?status=failed', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame([$failedJob->getIdentifier()->toString()], array_column($statusResponse->json()['props']['jobs'] ?? [], 'id'));

        $typeResponse = $this->get('/admin/jobs?type=package.delete', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame([$completedJob->getIdentifier()->toString()], array_column($typeResponse->json()['props']['jobs'] ?? [], 'id'));

        $organizationResponse = $this->get('/admin/jobs?organization_id=' . $organizationDto->id, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame([$failedJob->getIdentifier()->toString()], array_column($organizationResponse->json()['props']['jobs'] ?? [], 'id'));

        $deletionRunResponse = $this->get('/admin/jobs?deletion_run_id=run-admin-jobs', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame([$failedJob->getIdentifier()->toString()], array_column($deletionRunResponse->json()['props']['jobs'] ?? [], 'id'));

        $searchResponse = $this->get('/admin/jobs?search=' . $failedJob->getIdentifier()->toString(), [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame([$failedJob->getIdentifier()->toString()], array_column($searchResponse->json()['props']['jobs'] ?? [], 'id'));

        $warningResponse = $this->get('/admin/jobs?has_warnings=1', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame([$failedJob->getIdentifier()->toString()], array_column($warningResponse->json()['props']['jobs'] ?? [], 'id'));

        $sortResponse = $this->get('/admin/jobs?sort=priority&direction=desc', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $sortedIds = array_column($sortResponse->json()['props']['jobs'] ?? [], 'id');
        self::assertSame($completedJob->getIdentifier()->toString(), $sortedIds[0] ?? null);
        self::assertSame($failedJob->getIdentifier()->toString(), $sortedIds[1] ?? null);
    }

    #[Test]
    public function itShowsSystemJobDetailsWithoutAnOrganization(): void
    {
        $userDto       = $this->createUser('system-job-admin', ['admin']);
        $jobRepository = $this->getService(JobRepositoryInterface::class);
        $job           = Job::schedule('system:identity', 'identity.auth_mail', [
            'encrypted_request' => 'encrypted-test-payload',
        ]);
        $jobRepository->save($job);

        $this->logIn($userDto->email);
        $testResponse = $this->get('/admin/jobs/' . $job->getIdentifier()->toString(), [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Admin/JobDetails');
        $props = $testResponse->json()['props'];
        self::assertSame($job->getIdentifier()->toString(), $props['job']['id']);
        self::assertSame('system:identity', $props['job']['organizationId']);
        self::assertSame('identity.auth_mail', $props['job']['type']);
        self::assertArrayHasKey('organization_record', $props);
        self::assertNull($props['organization_record']);
    }

    #[Test]
    public function itShowsAdminJobDetailsWithLogsAndPayload(): void
    {
        $userDto         = $this->createUser('job-details-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto);

        /** @var JobRepositoryInterface $jobRepo */
        $jobRepo = $this->getService(JobRepositoryInterface::class);

        /** @var JobLogRepositoryInterface $jobLogRepo */
        $jobLogRepo = $this->getService(JobLogRepositoryInterface::class);
        $job        = Job::schedule($organizationDto->id, 'organization.delete', [
            'organization_id' => $organizationDto->id,
            'stage'           => 'finalize',
            'deletion_run_id' => 'run-details',
        ]);
        $job->markRunning('worker-details');
        $job->markFailed('finalize failed', 'stack trace');
        $jobRepo->save($job);
        $jobLogRepo->save(JobLog::record($job->getIdentifier()->toString(), JobLog::LEVEL_ERROR, 'Finalize failed', [
            'deletion_run_id' => 'run-details',
        ]));

        $this->logIn($userDto->email);

        $returnTo     = '/admin/jobs?status=failed&page=3&deletion_run_id=run-details';
        $testResponse = $this->get('/admin/jobs/' . $job->getIdentifier()->toString() . '?return_to=' . rawurlencode($returnTo), [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(200)
            ->assertInertiaComponent('Admin/JobDetails')
        ;

        $props = $testResponse->json()['props'] ?? [];
        self::assertSame($job->getIdentifier()->toString(), $props['job']['id'] ?? null);
        self::assertSame('run-details', $props['job']['payload']['deletion_run_id'] ?? null);
        self::assertSame('stack trace', $props['job']['errorDetails'] ?? null);
        self::assertSame('run-details', $props['deletion_run_id'] ?? null);
        self::assertSame($returnTo, $props['back_url'] ?? null);
        self::assertSame('Finalize failed', $props['logs'][0]['message'] ?? null);

        $listProps = $this->get('/admin/jobs', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->json()['props'];
        $listJob = $listProps['jobs'][0];
        self::assertSame($props['organization_record'], $listJob['organization']);
        self::assertSame($props['deletion_run_id'], $listJob['deletionRunId']);
        self::assertArrayNotHasKey('errorDetails', $listJob);
        self::assertArrayNotHasKey('organization', $props['job']);
        self::assertArrayNotHasKey('deletionRunId', $props['job']);
        unset($listJob['organization'], $listJob['deletionRunId']);
        $detailsJob = $props['job'];
        unset($detailsJob['errorDetails']);
        self::assertSame($detailsJob, $listJob);

        $fallbackResponse = $this->get('/admin/jobs/' . $job->getIdentifier()->toString() . '?return_to=https%3A%2F%2Fevil.example', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame('/admin/jobs', $fallbackResponse->json()['props']['back_url'] ?? null);
    }

    #[Test]
    public function itRejectsDestructiveLifecycleRestartAndRestartsOtherJobsWithoutCopyingIdempotencyKey(): void
    {
        $userDto         = $this->createUser('job-restart-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto);

        /** @var JobRepositoryInterface $jobRepo */
        $jobRepo = $this->getService(JobRepositoryInterface::class);

        /** @var JobLogRepositoryInterface $jobLogRepo */
        $jobLogRepo = $this->getService(JobLogRepositoryInterface::class);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations                        = $this->getService(OrganizationApiFacadeInterface::class);
        $organizationDeletionInitiationResult = $organizations->deleteOrganizationWithOutcome($organizationDto->id);
        self::assertNotNull($organizationDeletionInitiationResult->jobId);

        $job = $jobRepo->findByOrganization($organizationDto->id, [
            'type' => 'organization.delete',
        ])[0] ?? null;
        self::assertInstanceOf(Job::class, $job);
        $job->markRunning('worker-restart');
        $job->markFailed('attempt limit reached');
        $jobRepo->save($job);

        $completed = Job::schedule($organizationDto->id, 'package.delete', [
            'deletion_run_id' => 'run-completed',
        ], priority: 2, idempotencyKey: 'package-delete:one');
        $completed->markRunning('worker-restart');
        $completed->markCompleted();
        $jobRepo->save($completed);

        $pending = Job::schedule($organizationDto->id, 'source.delete', [
            'deletion_run_id' => 'run-pending',
        ], priority: 3, idempotencyKey: 'source-delete:one');
        $jobRepo->save($pending);

        $this->logIn($userDto->email);

        $this->withCsrf()
            ->post('/admin/jobs/' . $job->getIdentifier()->toString() . '/restart')
            ->assertRedirect('/admin/jobs')
        ;
        $this->withCsrf()
            ->post('/admin/jobs/' . $completed->getIdentifier()->toString() . '/restart')
            ->assertRedirect('/admin/jobs/')
        ;
        $this->withCsrf()
            ->post('/admin/jobs/' . $pending->getIdentifier()->toString() . '/restart')
            ->assertRedirect('/admin/jobs/' . $pending->getIdentifier()->toString())
        ;

        $organizationDeleteJobs = $jobRepo->findByOrganization($organizationDto->id, [
            'type' => 'organization.delete',
        ]);
        self::assertCount(2, $organizationDeleteJobs);
        $restartedOrganizationJobs = array_values(array_filter(
            $organizationDeleteJobs,
            static fn (Job $organizationDeleteJob): bool => $organizationDeleteJob->getIdentifier()->toString() !== $job->getIdentifier()->toString(),
        ));
        self::assertCount(1, $restartedOrganizationJobs);
        self::assertSame(Job::STATUS_PENDING, $restartedOrganizationJobs[0]->status());
        self::assertStringStartsWith(
            'organization-delete-recovery:' . $organizationDto->id . ':' . $organizationDeletionInitiationResult->deletionRunId . ':schedule_cleanup',
            (string) $restartedOrganizationJobs[0]->idempotencyKey(),
        );
        self::assertCount(1, $jobRepo->findByOrganization($organizationDto->id, [
            'type' => 'source.delete',
        ]));

        $restarted         = null;
        $packageDeleteJobs = $jobRepo->findByOrganization($organizationDto->id, [
            'type' => 'package.delete',
        ]);
        self::assertCount(2, $packageDeleteJobs);
        foreach ($packageDeleteJobs as $packageDeleteJob) {
            if ($packageDeleteJob->getIdentifier()->toString() !== $completed->getIdentifier()->toString()) {
                $restarted = $packageDeleteJob;

                break;
            }
        }

        self::assertInstanceOf(Job::class, $restarted);
        self::assertSame(Job::STATUS_PENDING, $restarted->status());
        self::assertSame(0, $restarted->attemptCount());
        self::assertNull($restarted->idempotencyKey());
        self::assertSame($completed->payload(), $restarted->payload());
        self::assertSame($completed->maxAttempts(), $restarted->maxAttempts());

        $newLogs = $jobLogRepo->findByJob($restarted->getIdentifier()->toString());
        self::assertSame('Job restarted by admin', $newLogs[0]->message());
        self::assertSame($userDto->id, $newLogs[0]->context()['admin_user_id'] ?? null);
        self::assertSame('package-delete:one', $newLogs[0]->context()['original_idempotency_key'] ?? null);

        $originalLogs = $jobLogRepo->findByJob($completed->getIdentifier()->toString());
        self::assertSame('Admin restart scheduled', $originalLogs[0]->message());
    }

    #[Test]
    public function itRestartsTheCurrentOrganizationFinalizeStageThroughTheOwningScheduler(): void
    {
        $userDto         = $this->createUser('organization-finalize-restart-admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations                        = $this->getService(OrganizationApiFacadeInterface::class);
        $organizationDeletionInitiationResult = $organizations->deleteOrganizationWithOutcome($organizationDto->id);
        self::assertNotNull($organizationDeletionInitiationResult->deletionRunId);

        /** @var JobRepositoryInterface $jobs */
        $jobs           = $this->getService(JobRepositoryInterface::class);
        $failedFinalize = Job::schedule(
            organizationId: $organizationDto->id,
            type: 'organization.delete',
            payload: [
                'organization_id' => $organizationDto->id,
                'deletion_run_id' => $organizationDeletionInitiationResult->deletionRunId,
                'stage'           => 'finalize',
            ],
            priority: 5,
            idempotencyKey: 'organization-delete:' . $organizationDto->id . ':' . $organizationDeletionInitiationResult->deletionRunId . ':finalize-failed',
        );
        $failedFinalize->markRunning('worker-finalize');
        $failedFinalize->markFailed('finalize failed');
        $jobs->save($failedFinalize);

        $this->logIn($userDto->email);
        $this->withCsrf()
            ->post('/admin/jobs/' . $failedFinalize->getIdentifier()->toString() . '/restart')
            ->assertRedirect('/admin/jobs/')
        ;

        $restartedFinalizers = array_values(array_filter(
            $jobs->findByOrganization($organizationDto->id, [
                'type' => 'organization.delete',
            ]),
            static fn (Job $job): bool => 'finalize' === ($job->payload()['stage'] ?? null)
                && $job->getIdentifier()->toString() !== $failedFinalize->getIdentifier()->toString(),
        ));
        self::assertCount(1, $restartedFinalizers);
        self::assertSame(Job::STATUS_PENDING, $restartedFinalizers[0]->status());
        self::assertSame(
            $organizationDeletionInitiationResult->deletionRunId,
            $restartedFinalizers[0]->payload()['deletion_run_id'] ?? null,
        );
        self::assertSame($userDto->id, $restartedFinalizers[0]->payload()['requested_by_user_id'] ?? null);

        $this->withCsrf()
            ->post('/admin/jobs/' . $failedFinalize->getIdentifier()->toString() . '/restart')
            ->assertRedirect('/admin/jobs/')
        ;
        self::assertCount(1, array_filter(
            $jobs->findByOrganization($organizationDto->id, [
                'type' => 'organization.delete',
            ]),
            static fn (Job $job): bool => 'finalize' === ($job->payload()['stage'] ?? null)
                && $job->getIdentifier()->toString() !== $failedFinalize->getIdentifier()->toString(),
        ));

        $restartedFinalizers[0]->markRunning('worker-finalize-retry');
        $restartedFinalizers[0]->markFailed('restarted finalizer failed');
        $jobs->save($restartedFinalizers[0]);

        $this->withCsrf()
            ->post('/admin/jobs/' . $failedFinalize->getIdentifier()->toString() . '/restart')
            ->assertRedirect('/admin/jobs/')
        ;

        $secondRestartedFinalizers = array_values(array_filter(
            $jobs->findByOrganization($organizationDto->id, [
                'type' => 'organization.delete',
            ]),
            static fn (Job $job): bool => 'finalize' === ($job->payload()['stage'] ?? null)
                && ! in_array($job->getIdentifier()->toString(), [
                    $failedFinalize->getIdentifier()->toString(),
                    $restartedFinalizers[0]->getIdentifier()->toString(),
                ], true),
        ));
        self::assertCount(1, $secondRestartedFinalizers);
        self::assertSame(Job::STATUS_PENDING, $secondRestartedFinalizers[0]->status());

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($secondRestartedFinalizers[0]);
        self::assertNull($organizations->findOrganization($organizationDto->id));
    }

    #[Test]
    public function itLoadsOrganizationDetailsForAdmin(): void
    {
        $userDto         = $this->createUser('admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto);
        $sourceDto       = $this->createSource($organizationDto, 'admin-org-details-source', 'github');
        $this->createPackage(
            $organizationDto,
            'admin/org-details-' . bin2hex(random_bytes(4)),
            sourceId: $sourceDto->id,
            sourceUrl: $sourceDto->url,
        );
        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin/organizations/' . $organizationDto->id, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(200)
            ->assertInertiaComponent('Admin/OrganizationDetails')
        ;

        $record = $testResponse->json()['props']['organization_record'] ?? [];
        self::assertSame($organizationDto->id, $record['id'] ?? null);
        self::assertSame($organizationDto->slug, $record['slug'] ?? null);
        self::assertSame(1, $record['members_count'] ?? null);
        self::assertSame(1, $record['sources_count'] ?? null);
        self::assertSame(1, $record['packages_count'] ?? null);
        self::assertArrayNotHasKey('invitations', $record);
        self::assertSame($userDto->id, $record['members'][0]['user_id'] ?? null);
        self::assertSame($userDto->email, $record['members'][0]['email'] ?? null);
    }

    #[Test]
    public function itPaginatesOrganizationMembersAndKeepsDeletionTotalsExact(): void
    {
        $userDto         = $this->createUser('admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        for ($index = 0; $index < 20; ++$index) {
            $member = $this->getService(IdentityCommandService::class)->createUserWithoutPassword(
                'member-' . $index . '-' . bin2hex(random_bytes(6)) . '@example.test',
                'Member-' . $index . ' User',
            );
            $identity->markUserEmailAsVerified($member->getIdentifier()->toString());
            $organizations->addMember($organizationDto->id, $member->getIdentifier()->toString());
        }
        $organizations->createInvitation($organizationDto->id, $userDto->id);
        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin/organizations/' . $organizationDto->id . '?members_page=2&members_per_page=10', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(200)
            ->assertInertiaComponent('Admin/OrganizationDetails')
        ;

        $record = $testResponse->json()['props']['organization_record'] ?? [];
        self::assertSame(21, $record['members_count'] ?? null);
        self::assertSame(1, $record['invitations_count'] ?? null);
        self::assertCount(10, $record['members'] ?? []);
        self::assertSame(2, $record['members_pagination']['current_page'] ?? null);
        self::assertSame(10, $record['members_pagination']['per_page'] ?? null);
        self::assertSame(21, $record['members_pagination']['total'] ?? null);
        self::assertSame(3, $record['members_pagination']['last_page'] ?? null);
    }

    #[Test]
    public function itUpdatesProjectRegistrationSetting(): void
    {
        $userDto = $this->createUser('admin', ['admin']);
        $this->logIn($userDto->email);

        /** @var ProjectSettingsApiFacadeInterface $settings */
        $settings = $this->getService(ProjectSettingsApiFacadeInterface::class);

        $this->withCsrf()->post('/admin/settings', [
            'settings' => [
                ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::Off->value,
                ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC   => RuntimeSettingOverride::Off->value,
            ],
        ])->assertRedirect('/admin/settings');

        self::assertFalse($settings->registrationOpen());
        self::assertSame(RuntimeSettingOverride::Off->value, $settings->overrideValue(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN));
        self::assertFalse($settings->booleanValue(ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC, true));
        self::assertSame(RuntimeSettingOverride::Off->value, $settings->overrideValue(ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC));

        $this->withCsrf()->post('/admin/settings', [
            'settings' => [
                ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::On->value,
                ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC   => RuntimeSettingOverride::On->value,
            ],
        ])->assertRedirect('/admin/settings');

        self::assertTrue($settings->registrationOpen());
        self::assertSame(RuntimeSettingOverride::On->value, $settings->overrideValue(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN));
        self::assertTrue($settings->booleanValue(ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC, false));
        self::assertSame(RuntimeSettingOverride::On->value, $settings->overrideValue(ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC));

        $this->withCsrf()->post('/admin/settings', [
            'settings' => [
                ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::Inherit->value,
                ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC   => RuntimeSettingOverride::Inherit->value,
            ],
        ])->assertRedirect('/admin/settings');

        self::assertSame(RuntimeSettingOverride::Inherit->value, $settings->overrideValue(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN));
        self::assertSame(RuntimeSettingOverride::Inherit->value, $settings->overrideValue(ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC));
    }

    #[Test]
    public function itRendersRuntimeSettingsPayload(): void
    {
        $userDto = $this->createUser('admin', ['admin']);
        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin/settings', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(200)
            ->assertInertiaComponent('Admin/Settings')
        ;

        $settings = $testResponse->json()['props']['settings'] ?? [];
        self::assertIsArray($settings['definitions'] ?? null);
        self::assertIsArray($settings['values'] ?? null);
        self::assertArrayHasKey(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN, $settings['values']);
        self::assertArrayHasKey(ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC, $settings['values']);
        self::assertSame(RuntimeSettingOverride::Inherit->value, $settings['values'][ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN]['override'] ?? null);
        self::assertSame(RuntimeSettingOverride::Inherit->value, $settings['values'][ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC]['override'] ?? null);
        self::assertSame(true, $settings['values'][ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC]['fallback'] ?? null);
        self::assertSame('api.docs.public', $settings['values'][ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC]['fallback_source'] ?? null);
    }

    #[Test]
    public function itVerifiesUserEmailByAdmin(): void
    {
        $userDto = $this->createUser('admin', ['admin']);
        $target  = $this->createUser('not-verified', ['user'], false);

        $this->logIn($userDto->email);
        $testResponse = $this->post('/admin/users/' . $target->id . '/verify');
        $testResponse->assertStatus(200)->assertJsonPath('success', true);

        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $updated  = $identity->findUserById($target->id);
        self::assertNotNull($updated);
        self::assertTrue($updated->isEmailVerified);
    }

    #[Test]
    public function itAllowsAdminToDeleteOrganizationWithCorrectConfirmation(): void
    {
        $userDto         = $this->createUser('admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'delete-able-' . bin2hex(random_bytes(8)));

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertEmpty($jobs->findByOrganization($organizationDto->id, [
            'type' => 'organization.delete',
        ]));

        $this->logIn($userDto->email);

        $this->withCsrf()->post(
            '/admin/organizations/' . $organizationDto->id . '/delete',
            [
                'confirmationName' => $organizationDto->name,
            ],
        )->assertRedirect('/admin/organizations');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations      = $this->getService(OrganizationApiFacadeInterface::class);
        $queuedOrganization = $organizations->findOrganization($organizationDto->id);
        self::assertNotNull($queuedOrganization);
        self::assertSame('deleting', $queuedOrganization->status);
        self::assertFalse($queuedOrganization->active);

        $deleteJobs = $jobs->findByOrganization($organizationDto->id, [
            'type' => 'organization.delete',
        ]);
        self::assertCount(1, $deleteJobs);
        self::assertSame('organization.delete', $deleteJobs[0]->type());
        self::assertSame($organizationDto->id, $deleteJobs[0]->payload()['organization_id'] ?? null);
        self::assertSame('schedule_cleanup', $deleteJobs[0]->payload()['stage'] ?? null);
        $deletionRunId = $deleteJobs[0]->payload()['deletion_run_id'];
        self::assertIsString($deletionRunId);
        self::assertNotSame('', $deletionRunId);
        self::assertSame(
            'organization-delete:' . $organizationDto->id . ':' . $deletionRunId,
            $deleteJobs[0]->idempotencyKey(),
        );

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($deleteJobs[0]);

        self::assertNotNull($organizations->findOrganization($organizationDto->id));

        $finalizeJobs = [];
        foreach ($jobs->findByOrganization($organizationDto->id, [
            'type' => 'organization.delete',
        ]) as $job) {
            if (($job->payload()['stage'] ?? null) === 'finalize') {
                $finalizeJobs[] = $job;
            }
        }

        self::assertCount(1, $finalizeJobs);
        $jobProcessor->process($finalizeJobs[0]);

        self::assertNull($organizations->findOrganization($organizationDto->id));
    }

    #[Test]
    public function itAuditsOrganizationDeletionInitiatedByAdmin(): void
    {
        $userDto         = $this->createUser('admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'delete-audit-' . bin2hex(random_bytes(8)));

        $this->logIn($userDto->email);

        $this->withCsrf()->post(
            '/admin/organizations/' . $organizationDto->id . '/delete',
            [
                'confirmationName' => $organizationDto->name,
            ],
        )->assertRedirect('/admin/organizations');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations      = $this->getService(OrganizationApiFacadeInterface::class);
        $queuedOrganization = $organizations->findOrganization($organizationDto->id);
        self::assertNotNull($queuedOrganization);
        self::assertSame('deleting', $queuedOrganization->status);

        $auditEntryDto = $this->latestOrganizationAuditEntry(
            $organizationDto->id,
            'organization.delete.initiated_by_admin',
        );
        self::assertSame($userDto->id, $auditEntryDto->actorId);
        self::assertSame('organization', $auditEntryDto->subjectType);
        self::assertSame($organizationDto->id, $auditEntryDto->subjectId);
        self::assertSame('admin-organization-delete', $auditEntryDto->context['action_origin'] ?? null);
        self::assertTrue($auditEntryDto->context['queued'] ?? null);
    }

    #[Test]
    public function itRejectsMismatchedOrganizationDeletionConfirmationWithoutDeletionOrAudit(): void
    {
        $userDto         = $this->createUser('admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'delete-mismatch-' . bin2hex(random_bytes(8)));

        $this->logIn($userDto->email);

        $this->withCsrf()->post(
            '/admin/organizations/' . $organizationDto->id . '/delete',
            [
                'confirmationName' => 'wrong-name',
            ],
        )->assertRedirect('/admin/organizations/' . $organizationDto->id);

        $testResponse = $this->get('/admin/organizations/' . $organizationDto->id, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame(
            'Type the exact organization name to confirm deletion.',
            $testResponse->json()['flash']['error'] ?? null,
        );

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertSame('active', $organizations->findOrganization($organizationDto->id)?->status);

        /** @var AuditApiFacadeInterface $audit */
        $audit = $this->getService(AuditApiFacadeInterface::class);
        foreach ($audit->recentBySubject('organization', $organizationDto->id, 20) as $auditEntryDto) {
            self::assertNotSame('organization.delete.initiated_by_admin', $auditEntryDto->event);
        }
    }

    #[Test]
    public function itIdempotentlyRetriesOrganizationDeletionInitiationWithoutDuplicateJobOrAudit(): void
    {
        $userDto         = $this->createUser('admin', ['admin']);
        $organizationDto = $this->createOrganization($userDto, 'delete-repeat-' . bin2hex(random_bytes(8)));

        $this->logIn($userDto->email);

        $this->withCsrf()->post(
            '/admin/organizations/' . $organizationDto->id . '/delete',
            [
                'confirmationName' => $organizationDto->name,
            ],
        )->assertRedirect('/admin/organizations');

        $this->withCsrf()->post(
            '/admin/organizations/' . $organizationDto->id . '/delete',
            [
                'confirmationName' => $organizationDto->name,
            ],
        )->assertRedirect('/admin/organizations');

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertCount(1, $jobs->findByOrganization($organizationDto->id, [
            'type' => 'organization.delete',
        ]));

        /** @var AuditApiFacadeInterface $audit */
        $audit             = $this->getService(AuditApiFacadeInterface::class);
        $initiationEntries = array_filter(
            $audit->recentBySubject('organization', $organizationDto->id, 20),
            static fn (AuditEntryDto $auditEntryDto): bool => 'organization.delete.initiated_by_admin' === $auditEntryDto->event,
        );
        self::assertCount(1, $initiationEntries);
    }

    /**
     * @param array<array<string, mixed>> $jobs
     */
    private function jobWarningCount(array $jobs, string $jobId): ?int
    {
        foreach ($jobs as $job) {
            if (($job['id'] ?? null) === $jobId) {
                return $job['warningCount'] ?? null;
            }
        }

        return null;
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $prefix, array $roles = ['user'], bool $verified = true): UserDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            $prefix . '-' . bin2hex(random_bytes(6)) . '@example.test',
            ucfirst($prefix) . ' User',
            self::PASSWORD,
            $roles,
        );

        if ($verified) {
            $identity->markUserEmailAsVerified($userDto->id);
        }

        return $userDto;
    }

    private function adminOrganizationFlash(string $url, string $type): ?string
    {
        return $this->get($url, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->json()['flash'][$type] ?? null;
    }

    private function createOrganization(UserDto $userDto, ?string $name = null): OrganizationDto
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        return $organizations->registerOrganization(
            $name ?? ('admin-org-' . bin2hex(random_bytes(6))),
            $userDto->id,
        );
    }

    private function createSource(OrganizationDto $organizationDto, string $name, string $provider = 'github'): SourceDto
    {
        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);

        return $sources->registerSource(new SourceRegistrationRequestDto(
            $provider,
            $this->sourceUrl($provider, $name),
            $organizationDto->id,
        ));
    }

    private function sourceUrl(string $provider, string $suffix): string
    {
        return match ($provider) {
            'github'      => 'https://github.com/org/' . $suffix,
            'gitlab'      => 'https://gitlab.com/org/' . $suffix,
            'generic_git' => 'https://git.example.test/acme/' . $suffix . '.git',
            default       => 'https://example.test/' . $suffix,
        };
    }

    private function createPackage(
        OrganizationDto $organizationDto,
        string $packageName,
        string $visibility = 'public',
        string $sourceId = '',
        string $sourceUrl = '',
    ): void {
        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        $packages->upsert(new PackageDto(
            name: $packageName,
            type: 'library',
            description: 'Admin package feature test',
            visibility: $visibility,
            sourceType: '' !== $sourceId ? 'github' : 'manual',
            sourceUrl: '' !== $sourceUrl ? $sourceUrl : 'https://example.test/' . str_replace('/', '-', $packageName),
            createdAt: '2026-05-20T00:00:00+00:00',
            updatedAt: '2026-05-21T00:00:00+00:00',
            organizationId: $organizationDto->id,
            sourceId: $sourceId,
            downloadCount: '' !== $sourceId ? 7 : 0,
        ));
    }

    private function markSourceDeletingWithoutRetainedJob(SourceDto $sourceDto): void
    {
        /** @var SourceRepositoryInterface $sources */
        $sources = $this->getService(SourceRepositoryInterface::class);
        $source  = $sources->findByIdInOrganization($sourceDto->id, $sourceDto->organizationId);

        self::assertInstanceOf(Source::class, $source);
        $source->markDeleting();
        $sources->save($source);
    }

    /**
     * @param array<int, mixed> $sources
     *
     * @return array<string, mixed>
     */
    private function sourceRow(array $sources, string $sourceId): array
    {
        foreach ($sources as $source) {
            if (is_array($source) && ($source['id'] ?? null) === $sourceId) {
                return $source;
            }
        }

        self::fail('Source row not found: ' . $sourceId);
    }

    private function latestSourceAuditEntry(string $organizationId, string $sourceId, string $event): AuditEntryDto
    {
        /** @var AuditApiFacadeInterface $audit */
        $audit = $this->getService(AuditApiFacadeInterface::class);

        foreach ($audit->recentBySource($organizationId, $sourceId, 20) as $auditEntryDto) {
            if ($auditEntryDto->event === $event) {
                return $auditEntryDto;
            }
        }

        self::fail('Audit entry not found: ' . $event);
    }

    private function latestOrganizationAuditEntry(string $organizationId, string $event): AuditEntryDto
    {
        /** @var AuditApiFacadeInterface $audit */
        $audit = $this->getService(AuditApiFacadeInterface::class);

        foreach ($audit->recentBySubject('organization', $organizationId, 20) as $auditEntryDto) {
            if ($auditEntryDto->event === $event) {
                return $auditEntryDto;
            }
        }

        self::fail('Audit entry not found: ' . $event);
    }

    private function logIn(string $email): void
    {
        $this->withCsrf()->post('/auth/login', [
            'email'    => $email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');
    }

    private function demoteAndExpect(
        UserDto $target,
        string $flashType,
        string $flashMessage,
        bool $stillAdmin,
        ?UserDto $actor = null
    ): void {
        $this->logIn(($actor ?? $target)->email);

        $this->withCsrf()->post('/admin/users/' . $target->id, [
            'email'        => $target->email,
            'display_name' => $target->displayName,
            'roles'        => ['user'],
        ])->assertRedirect('/admin/users/' . $target->id);

        $testResponse = $this->get('/admin/users/' . $target->id, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame($flashMessage, $testResponse->json()['flash'][$flashType] ?? null);

        /** @var UserRepositoryInterface $users */
        $users = $this->getService(UserRepositoryInterface::class);
        $user  = $users->findById($target->id);
        self::assertInstanceOf(User::class, $user);
        self::assertSame($stillAdmin, in_array('admin', $user->roles(), true));
    }

    /** @param list<string> $roles */
    private function replaceRoles(string $userId, array $roles): void
    {
        /** @var UserRepositoryInterface $users */
        $users = $this->getService(UserRepositoryInterface::class);
        $user  = $users->findById($userId);

        self::assertInstanceOf(User::class, $user);

        $user->replaceRoles($roles);
        $users->save($user);
    }
}
