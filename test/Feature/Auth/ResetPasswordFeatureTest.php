<?php

declare(strict_types=1);

namespace PackioTest\Feature\Auth;

use Cake\Chronos\Chronos;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Domain\Entity\PasswordResetToken;
use Packio\Identity\Domain\Repository\PasswordResetTokenRepositoryInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

use function bin2hex;
use function hash;
use function random_bytes;

final class ResetPasswordFeatureTest extends FeatureTestCase
{
    #[Test]
    public function itResetsPasswordWithValidToken(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'reset-success-' . bin2hex(random_bytes(6)) . '@example.test',
            'Reset User',
            'Password1!',
        );
        $selector    = 'sel_' . bin2hex(random_bytes(8));
        $secret      = 'tok_' . bin2hex(random_bytes(16));
        $newPassword = 'NewPassword1!';

        $this->seedPasswordResetToken($user->id, $selector, $secret);

        $this->withCsrf()->post('/auth/reset-password', [
            'selector'              => $selector,
            'token'                 => $secret,
            'password'              => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect('/auth/login');

        self::assertNotNull($identity->authenticateCredentials($user->email, $newPassword));
    }

    #[Test]
    public function successfulResetConsumesTheTokenAndRevokesOtherPendingResetLinks(): void
    {
        $identity      = $this->getService(IdentityApiFacadeInterface::class);
        $user          = $identity->createUser('reset-replay-' . bin2hex(random_bytes(6)) . '@example.test', 'Reset User', 'Password1!');
        $selector      = 'sel_' . bin2hex(random_bytes(8));
        $otherSelector = 'sel_' . bin2hex(random_bytes(8));
        $this->seedPasswordResetToken($user->id, $selector, 'secret');
        $this->seedPasswordResetToken($user->id, $otherSelector, 'other-secret');
        $form = [
            'selector'              => $selector,
            'token'                 => 'secret',
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ];
        $this->withCsrf()->post('/auth/reset-password', $form)->assertRedirect('/auth/login');
        $this->withCsrf()->post('/auth/reset-password', $form)->assertRedirect('/auth/forgot-password');
        $form['selector'] = $otherSelector;
        $form['token']    = 'other-secret';
        $this->withCsrf()->post('/auth/reset-password', $form)->assertRedirect('/auth/forgot-password');
        self::assertNull($identity->authenticateCredentials($user->email, 'Password1!'));
        self::assertNotNull($identity->authenticateCredentials($user->email, 'NewPassword1!'));
    }

    #[Test]
    public function resettingThePasswordSignsOutExistingSessions(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser('reset-sessions-' . bin2hex(random_bytes(6)) . '@example.test', 'Reset User', 'Password1!');
        $identity->markUserEmailAsVerified($user->id);
        $this->withCsrf()->post('/auth/login', [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertRedirect('/organizations');
        $this->get('/profile')->assertStatus(200);
        $signedInCookies = new ReflectionProperty(FeatureTestCase::class, 'cookies')->getValue($this);

        $this->clearCookies();
        new ReflectionProperty(FeatureTestCase::class, 'csrfToken')->setValue($this, 'test-csrf-token');
        $selector = 'sel_' . bin2hex(random_bytes(8));
        $this->seedPasswordResetToken($user->id, $selector, 'secret');
        $this->withCsrf()->post('/auth/reset-password', [
            'selector'              => $selector,
            'token'                 => 'secret',
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertRedirect('/auth/login');

        new ReflectionProperty(FeatureTestCase::class, 'cookies')->setValue($this, $signedInCookies);
        $this->get('/profile')->assertRedirect('/auth/login');
    }

    #[Test]
    public function itRendersResetPasswordPageForValidToken(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'reset-page-' . bin2hex(random_bytes(6)) . '@example.test',
            'Reset User',
            'Password1!',
        );
        $selector = 'sel_' . bin2hex(random_bytes(8));
        $secret   = 'tok_' . bin2hex(random_bytes(16));

        $this->seedPasswordResetToken($user->id, $selector, $secret);

        $testResponse = $this->get('/auth/reset-password/' . $selector . '/' . $secret, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200);
        self::assertSame('Auth/ResetPassword', $testResponse->json()['component'] ?? null);
    }

    #[Test]
    public function itRedirectsToForgotPasswordForInvalidTokenDuringPageLoad(): void
    {
        $testResponse = $this->get('/auth/reset-password/invalid_selector/invalid_token', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertRedirect('/auth/forgot-password');
    }

    #[Test]
    public function itShowsValidationErrorsWhenResetFormIsInvalid(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'reset-validation-' . bin2hex(random_bytes(6)) . '@example.test',
            'Reset User',
            'Password1!',
        );

        $selector  = 'sel_' . bin2hex(random_bytes(8));
        $secret    = 'tok_' . bin2hex(random_bytes(16));
        $resetPage = '/auth/reset-password/' . $selector . '/' . $secret;

        $this->seedPasswordResetToken($user->id, $selector, $secret);

        $this->withCsrf()->post('/auth/reset-password', [
            'selector'              => $selector,
            'token'                 => $secret,
            'password'              => 'short',
            'password_confirmation' => 'short',
        ], [
            'Referer' => $resetPage,
        ])->assertRedirect($resetPage);

        $testResponse = $this->get($resetPage, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200);
        $props = $testResponse->json()['props'] ?? [];
        self::assertArrayHasKey('errors', $props);
        self::assertNotSame([], $props['errors']);
        self::assertArrayNotHasKey('selector', $props['old']);
        self::assertArrayNotHasKey('token', $props['old']);
    }

    #[Test]
    public function itRejectsUsedTokenOnResetSubmit(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'reset-used-' . bin2hex(random_bytes(6)) . '@example.test',
            'Used User',
            'Password1!',
        );
        $selector = 'sel_' . bin2hex(random_bytes(8));
        $secret   = 'tok_' . bin2hex(random_bytes(16));

        $this->seedPasswordResetToken($user->id, $selector, $secret, used: true);

        $this->withCsrf()->post('/auth/reset-password', [
            'selector'              => $selector,
            'token'                 => $secret,
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertRedirect('/auth/forgot-password');

        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertNotNull($identity->authenticateCredentials($user->email, 'Password1!'));
        self::assertNull($identity->authenticateCredentials($user->email, 'NewPassword1!'));
    }

    #[Test]
    public function itRejectsWrongTokenOnSubmitAndKeepsPasswordIntact(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'reset-wrong-' . bin2hex(random_bytes(6)) . '@example.test',
            'Wrong Token User',
            'Password1!',
        );
        $selector = 'sel_' . bin2hex(random_bytes(8));
        $secret   = 'tok_' . bin2hex(random_bytes(16));

        $this->seedPasswordResetToken($user->id, $selector, $secret);

        $this->withCsrf()->post('/auth/reset-password', [
            'selector'              => $selector,
            'token'                 => $secret . 'x',
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertRedirect('/auth/forgot-password');

        self::assertNotNull($identity->authenticateCredentials($user->email, 'Password1!'));
        self::assertNull($identity->authenticateCredentials($user->email, 'NewPassword1!'));
    }

    #[Test]
    public function itMarksExpiredTokenInvalidWhenLoadingResetPage(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'reset-expired-' . bin2hex(random_bytes(6)) . '@example.test',
            'Expired Token User',
            'Password1!',
        );
        $selector = 'sel_' . bin2hex(random_bytes(8));
        $secret   = 'tok_' . bin2hex(random_bytes(16));

        $this->seedPasswordResetToken(
            $user->id,
            $selector,
            $secret,
            new Chronos('-10 minutes'),
        );

        $testResponse = $this->get('/auth/reset-password/' . $selector . '/' . $secret, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertRedirect('/auth/forgot-password');
    }

    private function seedPasswordResetToken(
        string $userId,
        string $selector,
        string $secret,
        ?Chronos $expiresAt = null,
        bool $used = false,
    ): void {
        $repository = $this->getService(PasswordResetTokenRepositoryInterface::class);
        if (! $repository instanceof PasswordResetTokenRepositoryInterface) {
            return;
        }

        $passwordResetToken = new PasswordResetToken(
            userUuid: $userId,
            selector: $selector,
            tokenHash: hash('sha256', $secret),
            expiresAt: $expiresAt ?? new Chronos('+10 minutes'),
        );

        if ($used) {
            $passwordResetToken->markAsUsed();
        }

        $repository->save($passwordResetToken);
    }
}
