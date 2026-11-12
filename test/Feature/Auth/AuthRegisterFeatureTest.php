<?php

declare(strict_types=1);

namespace PackioTest\Feature\Auth;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Admin\Application\Settings\RuntimeSettingOverride;
use Packio\Admin\Application\Settings\RuntimeSettingsService;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\OAuth\OAuthCallbackUseCase;
use Packio\Identity\Application\Command\OAuth\ResolveOAuthCallbackCommand;
use Packio\Identity\Application\Result\OAuthCallbackStatus;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function getenv;
use function json_encode;
use function putenv;
use function random_bytes;
use function rawurlencode;

final class AuthRegisterFeatureTest extends FeatureTestCase
{
    /** @var array<string, ?string> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->originalEnv = [
            'OAUTH_GITHUB_ENABLED'       => $this->envValue('OAUTH_GITHUB_ENABLED'),
            'OAUTH_GITHUB_CLIENT_ID'     => $this->envValue('OAUTH_GITHUB_CLIENT_ID'),
            'OAUTH_GITHUB_CLIENT_SECRET' => $this->envValue('OAUTH_GITHUB_CLIENT_SECRET'),
            'OAUTH_GITLAB_ENABLED'       => $this->envValue('OAUTH_GITLAB_ENABLED'),
            'OAUTH_GITLAB_CLIENT_ID'     => $this->envValue('OAUTH_GITLAB_CLIENT_ID'),
            'OAUTH_GITLAB_CLIENT_SECRET' => $this->envValue('OAUTH_GITLAB_CLIENT_SECRET'),
            'OAUTH_BITBUCKET_ENABLED'    => $this->envValue('OAUTH_BITBUCKET_ENABLED'),
            'IDENTITY_REGISTRATION_OPEN' => $this->envValue('IDENTITY_REGISTRATION_OPEN'),
        ];

        $this->setEnv('OAUTH_GITHUB_ENABLED', 'true');
        $this->setEnv('OAUTH_GITHUB_CLIENT_ID', 'github-client');
        $this->setEnv('OAUTH_GITHUB_CLIENT_SECRET', 'github-secret');
        $this->setEnv('OAUTH_GITLAB_ENABLED', 'true');
        $this->setEnv('OAUTH_GITLAB_CLIENT_ID', 'gitlab-client');
        $this->setEnv('OAUTH_GITLAB_CLIENT_SECRET', 'gitlab-secret');
        $this->setEnv('OAUTH_BITBUCKET_ENABLED', 'false');
        $this->setEnv('IDENTITY_REGISTRATION_OPEN', 'true');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->originalEnv as $key => $value) {
            if (null === $value) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);

                continue;
            }

            $this->setEnv($key, $value);
        }
    }

    #[Test]
    public function removedEmailAvailabilityEndpointIsNotRoutable(): void
    {
        $this->get('/auth/check-email')->assertStatus(404);
        $this->withCsrf()->post('/auth/check-email', [
            'email' => 'user@example.test',
        ])->assertStatus(404);
    }

    #[Test]
    public function itRendersRegisterPage(): void
    {
        $this->get('/auth/register')->assertStatus(200);
    }

    #[Test]
    public function itPassesRegistrationOpenFlagToRegisterPage(): void
    {
        /** @var RuntimeSettingsService $settings */
        $settings = $this->getService(RuntimeSettingsService::class);
        $settings->updateOverrides([
            ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::Off->value,
        ], null);

        $testResponse = $this->get('/auth/register', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200);

        self::assertFalse($testResponse->json()['props']['registration_open'] ?? true);
    }

    #[Test]
    public function registrationOpenForPendingInvitationRedirect(): void
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity   = $this->getService(IdentityApiFacadeInterface::class);
        $userDto    = $identity->createUser('invite-owner-' . bin2hex(random_bytes(6)) . '@example.test', 'Owner', 'Password1!');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations                = $this->getService(OrganizationApiFacadeInterface::class);
        $organizationDto              = $organizations->registerOrganization('invite-open-' . bin2hex(random_bytes(4)), $userDto->id);
        $organizationInvitationDto    = $organizations->createInvitation($organizationDto->id, $userDto->id, 'member');

        /** @var RuntimeSettingsService $settings */
        $settings = $this->getService(RuntimeSettingsService::class);
        $settings->updateOverrides([
            ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::Off->value,
        ], null);

        $headers = [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ];

        foreach (['/auth/register', '/auth/login'] as $page) {
            $withInvitation = $this->get($page . '?redirect_to=' . rawurlencode('/organization/invite/' . $organizationInvitationDto->token), $headers);
            $withInvitation->assertStatus(200);
            self::assertTrue($withInvitation->json()['props']['registration_open'] ?? null, $page);

            $withoutInvitation = $this->get($page . '?redirect_to=' . rawurlencode('/organization/invite/orgi_missing'), $headers);
            $withoutInvitation->assertStatus(200);
            self::assertFalse($withoutInvitation->json()['props']['registration_open'] ?? null, $page);
        }
    }

    #[Test]
    public function pendingInvitationAllowsOAuthSignupWhenRegistrationIsClosed(): void
    {
        $identity      = $this->getService(IdentityApiFacadeInterface::class);
        $owner         = $identity->createUser('invite-owner-' . bin2hex(random_bytes(6)) . '@example.test', 'Owner', 'Password1!');
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $organization  = $organizations->registerOrganization('oauth-invite-' . bin2hex(random_bytes(4)), $owner->id);
        $invitation    = $organizations->createInvitation($organization->id, $owner->id, 'member');

        $this->getService(RuntimeSettingsService::class)->updateOverrides([
            ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::Off->value,
        ], null);

        $email   = 'oauth-invitee-' . bin2hex(random_bytes(6)) . '@example.test';
        $command = static fn (string $redirectTo): ResolveOAuthCallbackCommand => new ResolveOAuthCallbackCommand(
            OAuthProvider::Github,
            new OAuthTokenResponse('access-token', null, null),
            new OAuthUserData(bin2hex(random_bytes(6)), $email, 'invitee', 'Invitee', null),
            registrationRedirectTarget: $redirectTo,
        );
        $useCase = $this->getService(OAuthCallbackUseCase::class);

        self::assertSame(OAuthCallbackStatus::RegistrationDisabled, $useCase->handle($command(''))->status);
        self::assertNull($this->getService(UserRepositoryInterface::class)->findByEmail($email));

        $result = $useCase->handle($command('/organization/invite/' . $invitation->token));

        self::assertSame(OAuthCallbackStatus::Authenticated, $result->status);
        self::assertTrue($result->userCreated);
        self::assertNotNull($this->getService(UserRepositoryInterface::class)->findByEmail($email));
    }

    #[Test]
    public function itRejectsRegistrationWhenClosed(): void
    {
        /** @var RuntimeSettingsService $settings */
        $settings = $this->getService(RuntimeSettingsService::class);
        $settings->updateOverrides([
            ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::Off->value,
        ], null);

        $email = 'closed-registration-' . bin2hex(random_bytes(6)) . '@example.test';

        $this->withCsrf()->post('/auth/register', [
            'display_name'          => 'Closed Registration User',
            'email'                 => $email,
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect('/auth/login');

        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->authenticateCredentials($email, 'Password1!');

        self::assertNull($user);
    }

    #[Test]
    public function itPassesSafeRedirectTargetToRegisterPage(): void
    {
        $testResponse = $this->get('/auth/register?redirect_to=%2Forganization%2Finvite%2Forgi_test', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200);

        self::assertSame('/organization/invite/orgi_test', $testResponse->json()['props']['redirect_to'] ?? null);

        $external = $this->get('/auth/register?redirect_to=https%3A%2F%2Fevil.example%2Faccount-created', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $external->assertStatus(200);

        self::assertSame('', $external->json()['props']['redirect_to'] ?? null);
    }

    #[Test]
    public function itRegistersUserAndRedirectsToVerification(): void
    {
        $email = 'new-user-' . bin2hex(random_bytes(6)) . '@example.test';

        $this->withCsrf()->post('/auth/register', [
            'display_name'          => 'New User',
            'email'                 => $email,
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect('/auth/verification');

        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->authenticateCredentials($email, 'Password1!');

        self::assertNotNull($user);
        self::assertSame($email, $user->email);
    }

    #[Test]
    public function repeatedRegistrationDoesNotRevealOrModifyExistingAccount(): void
    {
        $email = 'registration-private-' . bin2hex(random_bytes(6)) . '@example.test';
        $form  = [
            'display_name'          => 'Original User',
            'email'                 => $email,
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ];
        $this->withCsrf()->post('/auth/register', $form)->assertRedirect('/auth/verification');
        $testResponse = $this->get('/auth/verification', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200);
        $this->withCsrf()->post('/auth/register', [
            ...$form,
            'display_name'          => 'Attacker',
            'password'              => 'Different2!',
            'password_confirmation' => 'Different2!',
        ])->assertRedirect('/auth/verification');
        $repeat = $this->get('/auth/verification', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $repeat->assertStatus(200);
        self::assertSame($testResponse->json()['component'], $repeat->json()['component']);
        self::assertGreaterThanOrEqual(118, $repeat->json()['props']['resend_available_in']);
        self::assertEmpty($repeat->json()['props']['errors'] ?? []);
        $this->withCsrf()->post('/auth/verification/send')->assertJsonPath('success', true);
        $this->withCsrf()->post('/auth/verification/resend')->assertRedirect('/auth/verification');
        $this->withCsrf()->post('/auth/verification/verify', [
            'code' => '123456',
        ])->assertRedirect('/auth/verification');
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertNull($identity->authenticateCredentials($email, 'Different2!'));
        $user = $identity->authenticateCredentials($email, 'Password1!');
        self::assertNotNull($user);
        self::assertSame('Original User', $user->displayName);
    }

    #[Test]
    public function itIgnoresExternalRedirectUrlDuringRegister(): void
    {
        $this->withCsrf()->post('/auth/register', [
            'display_name'          => 'Redirected User',
            'email'                 => 'redirected-user-' . bin2hex(random_bytes(6)) . '@example.test',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
            'redirect_to'           => 'https://evil.example/account-created',
        ])->assertRedirect('/auth/verification');
    }

    #[Test]
    public function itPreservesFormStateAfterWeakPasswordValidationError(): void
    {
        $email = 'weak-password-' . bin2hex(random_bytes(6)) . '@example.test';

        $this->get('/auth/register', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $this->withCsrf()->post('/auth/register', [
            'display_name'          => 'Weak Password User',
            'email'                 => $email,
            'password'              => '12345678',
            'password_confirmation' => '12345678',
        ])->assertRedirect('/auth/register');

        $testResponse = $this->get('/auth/register', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200);

        $page = $testResponse->json();
        self::assertIsArray($page['props'] ?? null);
        self::assertIsArray($page['props']['errors'] ?? null);
        self::assertArrayHasKey('password', $page['props']['errors']);
        self::assertNotSame('', $page['props']['errors']['password'][0] ?? null);
        self::assertStringContainsString('special', (string) $page['props']['errors']['password'][0]);
        self::assertSame('Weak Password User', $page['props']['old']['display_name'] ?? null);
        self::assertSame($email, $page['props']['old']['email'] ?? null);
    }

    #[Test]
    public function itUsesTheRegistrationValidationPolicyWhenBodyMappingFails(): void
    {
        $email  = 'mapping-error-' . bin2hex(random_bytes(6)) . '@example.test';
        $secret = 'redirect-state-secret-' . bin2hex(random_bytes(6));

        $this->withCsrf('/auth/register')->post('/auth/register', [
            'display_name'          => ['not-a-string'],
            'email'                 => $email,
            'password'              => $secret,
            'password_confirmation' => $secret,
            'redirect_to'           => '/organization/invite/orgi_test',
        ])->assertRedirect('/auth/register');

        $page = $this->get('/auth/register', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->json();

        self::assertNotSame('', $page['props']['errors']['display_name'][0] ?? null);
        self::assertSame(['not-a-string'], $page['props']['old']['display_name'] ?? null);
        self::assertSame($email, $page['props']['old']['email'] ?? null);
        self::assertSame('/organization/invite/orgi_test', $page['props']['old']['redirect_to'] ?? null);
        self::assertArrayNotHasKey('password', $page['props']['old'] ?? []);
        self::assertArrayNotHasKey('password_confirmation', $page['props']['old'] ?? []);
        self::assertArrayNotHasKey('error', $page['flash'] ?? []);
        self::assertArrayNotHasKey('toasts', $page['flash'] ?? []);
        self::assertStringNotContainsString($secret, json_encode($page, JSON_THROW_ON_ERROR));

        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertNull($identity->authenticateCredentials($email, $secret));
    }

    #[Test]
    public function itReturnsTheExistingJsonValidationFormForANonInertiaMappingFailure(): void
    {
        $testResponse = $this->withCsrf('/auth/register')->post('/auth/register', [
            'display_name' => ['not-a-string'],
            'email'        => 'mapping-json@example.test',
        ], [
            'Accept' => 'application/json',
        ]);

        $testResponse
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error', 'Invalid request data.')
        ;

        self::assertNotSame('', $testResponse->json()['messages']['display_name'][0] ?? null);
    }

    #[Test]
    public function itShowsValidationErrorForMalformedEmail(): void
    {
        $this->get('/auth/register', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $this->withCsrf()->post('/auth/register', [
            'display_name'          => 'Malformed Email User',
            'email'                 => 'not-an-email',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect('/auth/register');

        $testResponse = $this->get('/auth/register', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200);

        $page = $testResponse->json();
        self::assertNotSame('', $page['props']['errors']['email'][0] ?? null);
        self::assertSame('Malformed Email User', $page['props']['old']['display_name'] ?? null);
    }

    #[Test]
    public function itRendersOAuthProvidersPayloadOnRegisterPage(): void
    {
        $testResponse = $this->get('/auth/register', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200);

        $providers = $testResponse->json()['props']['oauth_providers'] ?? [];
        self::assertIsArray($providers);
        self::assertCount(4, $providers);
        self::assertSame('github', $providers[0]['provider'] ?? null);
        self::assertSame('GitHub', $providers[0]['label'] ?? null);
        self::assertSame('gitlab', $providers[1]['provider'] ?? null);
        self::assertSame('GitLab', $providers[1]['label'] ?? null);
        self::assertSame('bitbucket', $providers[2]['provider'] ?? null);
        self::assertSame('Bitbucket', $providers[2]['label'] ?? null);
        self::assertSame('codeberg', $providers[3]['provider'] ?? null);
        self::assertSame('Codeberg', $providers[3]['label'] ?? null);
        self::assertSame(true, $providers[0]['enabled'] ?? false);
        self::assertSame(true, $providers[1]['enabled'] ?? false);
        self::assertSame(false, $providers[2]['enabled'] ?? true);
        self::assertSame(false, $providers[3]['enabled'] ?? true);
    }

    private function setEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }

    private function envValue(string $key): ?string
    {
        $value = getenv($key);

        return false === $value ? null : $value;
    }
}
