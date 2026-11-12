<?php

declare(strict_types=1);

namespace PackioTest\Feature\Profile;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Http\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use RuntimeException;

use function bin2hex;
use function random_bytes;

/**
 * Regression coverage for the critical validation bypass: a strong new password
 * with a mismatched confirmation must never reach the password mutation.
 */
final class ChangePasswordFeatureTest extends FeatureTestCase
{
    private const string CURRENT_PASSWORD = 'Password1!';

    private const string NEW_PASSWORD = 'NewPassword2!';

    #[Test]
    public function itRejectsMismatchedNewPasswordConfirmationWithoutMutatingThePassword(): void
    {
        $userDto = $this->createPasswordUser();
        $this->logIn($userDto);

        $this->withCsrf()->post('/profile/password', [
            'current_password'          => self::CURRENT_PASSWORD,
            'new_password'              => self::NEW_PASSWORD,
            'new_password_confirmation' => 'Totally-Different3!',
        ])->assertRedirect();

        $testResponse = $this->get('/profile', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $errors = $testResponse->json()['props']['errors'] ?? [];

        self::assertArrayHasKey('new_password_confirmation', $errors);

        $oldPasswordLogin = $this->withCsrf()->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => self::CURRENT_PASSWORD,
        ]);

        self::assertSame(302, $oldPasswordLogin->status());
        self::assertStringNotContainsString('/auth/login', $oldPasswordLogin->header('Location'));

        $this->post('/auth/logout')->assertRedirect();

        $reflectionProperty = new ReflectionProperty(FeatureTestCase::class, 'csrfToken');
        $reflectionProperty->setValue($this, 'test-csrf-token');

        $newPasswordLogin = $this->withCsrf('/auth/login')->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => self::NEW_PASSWORD,
        ]);

        self::assertSame(302, $newPasswordLogin->status());
        self::assertSame('/auth/login', $newPasswordLogin->header('Location'));
    }

    #[Test]
    public function itChangesThePasswordWhenTheConfirmationMatches(): void
    {
        $userDto = $this->createPasswordUser();
        $this->logIn($userDto);

        $this->withCsrf()->post('/profile/password', [
            'current_password'          => self::CURRENT_PASSWORD,
            'new_password'              => self::NEW_PASSWORD,
            'new_password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect('/profile');
        // The session is re-issued with a rotated CSRF token; the browser picks it up from the /profile page.
        $this->loadCsrfToken('/profile');

        $this->post('/auth/logout')->assertRedirect();

        $reflectionProperty = new ReflectionProperty(FeatureTestCase::class, 'csrfToken');
        $reflectionProperty->setValue($this, 'test-csrf-token');

        $testResponse = $this->withCsrf('/auth/login')->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => self::NEW_PASSWORD,
        ]);

        self::assertSame(302, $testResponse->status());
        self::assertStringNotContainsString('/auth/login', $testResponse->header('Location'));
    }

    #[Test]
    public function changingThePasswordSignsOutOtherSessionsAndKeepsTheCurrentOne(): void
    {
        $userDto = $this->createPasswordUser();
        $this->logIn($userDto);
        $first = $this->browserState();

        $this->restoreBrowserState([
            'cookies'   => [],
            'csrfToken' => 'test-csrf-token',
        ]);
        $this->logIn($userDto);
        $second = $this->browserState();

        $this->restoreBrowserState($first);
        $this->withCsrf()->post('/profile/password', [
            'current_password'          => self::CURRENT_PASSWORD,
            'new_password'              => self::NEW_PASSWORD,
            'new_password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect('/profile');
        $this->get('/profile')->assertStatus(200);

        $this->restoreBrowserState($second);
        $this->get('/profile')->assertRedirect('/auth/login');
    }

    #[Test]
    public function validationFailureSessionStateContainsNoPasswordValues(): void
    {
        $userDto = $this->createPasswordUser();
        $this->logIn($userDto);

        $rejectedPassword = 'Leaked-Password9!';

        $this->withCsrf()->post('/profile/password', [
            'current_password'          => self::CURRENT_PASSWORD,
            'new_password'              => $rejectedPassword,
            'new_password_confirmation' => 'Mismatch4!',
        ])->assertRedirect();

        $testResponse = $this->get('/profile', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        self::assertStringNotContainsString($rejectedPassword, $testResponse->body());
    }

    #[Test]
    public function incorrectCurrentPasswordRendersTheSameProfileData(): void
    {
        $userDto = $this->createPasswordUser();
        $this->identity()->issueApiAccessTokenForUser($userDto->id, 'Profile contract token', ['read']);
        $this->logIn($userDto);

        $headers = [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ];
        $before       = $this->get('/profile', $headers)->json()['props'];
        $testResponse = $this->withCsrf()->post('/profile/password', [
            'current_password'          => 'WrongPassword3!',
            'new_password'              => self::NEW_PASSWORD,
            'new_password_confirmation' => self::NEW_PASSWORD,
        ], $headers);
        $testResponse->assertStatus(200);
        $after = $testResponse->json()['props'];

        self::assertSame('Profile contract token', $after['api_tokens'][0]['name']);
        foreach (['profile', 'oauth_accounts', 'api_tokens'] as $key) {
            self::assertSame($before[$key], $after[$key]);
        }
        self::assertSame(['Current password is incorrect.'], $after['errors']['current_password']);
    }

    #[Test]
    public function passwordChangeIsRateLimitedPerUser(): void
    {
        $userDto = $this->createPasswordUser();
        $this->logIn($userDto);

        for ($i = 0; $i < 5; ++$i) {
            $this->wrongPasswordAttempt()->assertStatus(200);
        }

        $this->wrongPasswordAttempt()->assertRedirect('/profile');
        $page = $this->get('/profile', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->json();
        self::assertSame('Too many requests. Please try again later.', $page['flash']['error'] ?? null);

        // Same IP, different user: not limited.
        $userB = $this->createPasswordUser();
        $this->restoreBrowserState([
            'cookies'   => [],
            'csrfToken' => 'test-csrf-token',
        ]);
        $this->logIn($userB);

        $this->wrongPasswordAttempt()->assertStatus(200);
    }

    private function wrongPasswordAttempt(): TestResponse
    {
        return $this->withCsrf()->post('/profile/password', [
            'current_password'          => 'WrongPassword3!',
            'new_password'              => self::NEW_PASSWORD,
            'new_password_confirmation' => self::NEW_PASSWORD,
        ], [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
    }

    private function createPasswordUser(): UserDto
    {
        $userDto = $this->identity()->createUser(
            'profile-password-' . bin2hex(random_bytes(6)) . '@example.test',
            'Profile Password User',
            self::CURRENT_PASSWORD,
        );
        $this->identity()->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function logIn(UserDto $userDto): void
    {
        $this->withCsrf()->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => self::CURRENT_PASSWORD,
        ])->assertRedirect('/organizations');
    }

    /** @return array{cookies: mixed, csrfToken: mixed} */
    private function browserState(): array
    {
        return [
            'cookies'   => new ReflectionProperty(FeatureTestCase::class, 'cookies')->getValue($this),
            'csrfToken' => new ReflectionProperty(FeatureTestCase::class, 'csrfToken')->getValue($this),
        ];
    }

    /** @param array{cookies: mixed, csrfToken: mixed} $state */
    private function restoreBrowserState(array $state): void
    {
        new ReflectionProperty(FeatureTestCase::class, 'cookies')->setValue($this, $state['cookies']);
        new ReflectionProperty(FeatureTestCase::class, 'csrfToken')->setValue($this, $state['csrfToken']);
    }

    private function identity(): IdentityApiFacadeInterface
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        if (! $identity instanceof IdentityApiFacadeInterface) {
            throw new RuntimeException('Identity API facade is not configured.');
        }

        return $identity;
    }
}
