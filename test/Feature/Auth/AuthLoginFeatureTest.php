<?php

declare(strict_types=1);

namespace PackioTest\Feature\Auth;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function getenv;
use function putenv;
use function random_bytes;

final class AuthLoginFeatureTest extends FeatureTestCase
{
    /** @var array<string, ?string> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->originalEnv = [
            'OAUTH_GITHUB_ENABLED'                    => $this->envValue('OAUTH_GITHUB_ENABLED'),
            'OAUTH_GITHUB_CLIENT_ID'                  => $this->envValue('OAUTH_GITHUB_CLIENT_ID'),
            'OAUTH_GITHUB_CLIENT_SECRET'              => $this->envValue('OAUTH_GITHUB_CLIENT_SECRET'),
            'OAUTH_GITLAB_ENABLED'                    => $this->envValue('OAUTH_GITLAB_ENABLED'),
            'OAUTH_GITLAB_CLIENT_ID'                  => $this->envValue('OAUTH_GITLAB_CLIENT_ID'),
            'OAUTH_GITLAB_CLIENT_SECRET'              => $this->envValue('OAUTH_GITLAB_CLIENT_SECRET'),
            'OAUTH_GITLAB_SELF_HOSTED_SOURCE_ENABLED' => $this->envValue('OAUTH_GITLAB_SELF_HOSTED_SOURCE_ENABLED'),
            'OAUTH_BITBUCKET_ENABLED'                 => $this->envValue('OAUTH_BITBUCKET_ENABLED'),
        ];

        $this->setEnv('OAUTH_GITHUB_ENABLED', 'true');
        $this->setEnv('OAUTH_GITHUB_CLIENT_ID', 'github-client');
        $this->setEnv('OAUTH_GITHUB_CLIENT_SECRET', 'github-secret');
        $this->setEnv('OAUTH_GITLAB_ENABLED', 'true');
        $this->setEnv('OAUTH_GITLAB_CLIENT_ID', 'gitlab-client');
        $this->setEnv('OAUTH_GITLAB_CLIENT_SECRET', 'gitlab-secret');
        $this->setEnv('OAUTH_GITLAB_SELF_HOSTED_SOURCE_ENABLED', 'true');
        $this->setEnv('OAUTH_BITBUCKET_ENABLED', 'false');

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
    public function itRedirectsUnverifiedUserToEmailVerification(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'member-' . bin2hex(random_bytes(6)) . '@example.test',
            'Member User',
            'Password1!',
        );

        $this->withCsrf()->post('/auth/login', [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertRedirect('/auth/verification');
    }

    #[Test]
    public function itRejectsLoginWhenHoneypotFieldIsFilled(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'honeypot-' . bin2hex(random_bytes(6)) . '@example.test',
            'Honeypot User',
            'Password1!',
        );
        $identity->markUserEmailAsVerified($user->id);

        $this->withCsrf()->post('/auth/login', [
            'email'              => $user->email,
            'password'           => 'Password1!',
            'h_0123456789abcdef' => 'bot',
        ])->assertRedirect('/auth/login');

        $testResponse = $this->get('/auth/login', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        self::assertSame(
            ['Please complete the security challenge.'],
            $testResponse->json()['props']['errors']['turnstile'] ?? null,
        );
    }

    #[Test]
    public function itAllowsLoginWhenHoneypotFieldIsEmpty(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'honeypot-ok-' . bin2hex(random_bytes(6)) . '@example.test',
            'Honeypot User',
            'Password1!',
        );
        $identity->markUserEmailAsVerified($user->id);

        $this->withCsrf()->post('/auth/login', [
            'email'              => $user->email,
            'password'           => 'Password1!',
            'h_0123456789abcdef' => '',
        ])->assertRedirect('/organizations');
    }

    #[Test]
    public function itIgnoresExternalRedirectUrlDuringLogin(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'member-' . bin2hex(random_bytes(6)) . '@example.test',
            'Member User',
            'Password1!',
        );
        $identity->markUserEmailAsVerified($user->id);

        $this->withCsrf()->post('/auth/login', [
            'email'       => $user->email,
            'password'    => 'Password1!',
            'redirect_to' => 'https://evil.example/steal-session',
        ])->assertRedirect('/organizations');
    }

    #[Test]
    public function itRedirectsVerifiedUserToSafeRedirectUrlDuringLogin(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'redirect-member-' . bin2hex(random_bytes(6)) . '@example.test',
            'Redirect Member',
            'Password1!',
        );
        $identity->markUserEmailAsVerified($user->id);

        $this->withCsrf()->post('/auth/login', [
            'email'       => $user->email,
            'password'    => 'Password1!',
            'redirect_to' => '/organization/invite/orgi_test',
        ])->assertRedirect('/organization/invite/orgi_test');
    }

    #[Test]
    public function itRefreshesSessionCookieDuringAuthenticatedUse(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'session-refresh-' . bin2hex(random_bytes(6)) . '@example.test',
            'Session Refresh',
            'Password1!',
        );
        $identity->markUserEmailAsVerified($user->id);

        $testResponse = $this->withCsrf()->post('/auth/login', [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertRedirect('/organizations');

        self::assertStringContainsString('PACKIO_SESSID=', $testResponse->header('Set-Cookie'));
        self::assertStringContainsString('Max-Age=7200', $testResponse->header('Set-Cookie'));
    }

    #[Test]
    public function itPassesSafeRedirectTargetToLoginPage(): void
    {
        $testResponse = $this->get('/auth/login?redirect_to=%2Forganization%2Finvite%2Forgi_test', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200);

        self::assertSame('/organization/invite/orgi_test', $testResponse->json()['props']['redirect_to'] ?? null);

        $external = $this->get('/auth/login?redirect_to=https%3A%2F%2Fevil.example%2Fsteal', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $external->assertStatus(200);

        self::assertSame('', $external->json()['props']['redirect_to'] ?? null);
    }

    #[Test]
    public function itPreservesRedirectToInertiaPropsAfterMalformedEmailValidationError(): void
    {
        $testResponse = $this->get('/auth/login', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200);

        $this->withCsrf()->post('/auth/login', [
            'email'       => 'not-an-email',
            'password'    => 'Password1!',
            'redirect_to' => '/organizations',
        ])->assertRedirect('/auth/login');

        $followUp = $this->get('/auth/login', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $followUp->assertStatus(200);

        $page = $followUp->json();
        self::assertIsArray($page['props'] ?? null);
        self::assertIsArray($page['props']['errors'] ?? null);
        self::assertArrayHasKey('email', $page['props']['errors']);
        self::assertIsArray($page['props']['errors']['email']);
        self::assertNotSame('', $page['props']['errors']['email'][0] ?? null);
        self::assertSame('/organizations', $page['props']['old']['redirect_to'] ?? null);
    }

    #[Test]
    public function itRendersOAuthProvidersPayloadOnLoginPage(): void
    {
        $testResponse = $this->get('/auth/login', [
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
