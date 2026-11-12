<?php

declare(strict_types=1);

namespace PackioTest\Feature\Auth;

use Cake\Chronos\Chronos;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Domain\Entity\EmailVerificationCode;
use Packio\Identity\Domain\Repository\EmailVerificationCodeRepositoryInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function json_encode;
use function password_hash;
use function random_bytes;

final class EmailVerificationFeatureTest extends FeatureTestCase
{
    #[Test]
    public function itRedirectsVerificationAttemptsWithoutPendingSessionToLogin(): void
    {
        $this->withCsrf()
            ->post('/auth/verification/verify', [
                'code' => '123456',
            ])
            ->assertRedirect('/auth/login')
        ;
    }

    #[Test]
    public function itRendersVerificationPageForPendingVerificationUser(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'pending-' . bin2hex(random_bytes(6)) . '@example.test',
            'Pending User',
            'Password1!',
        );

        $this->withCsrf()->post('/auth/login', [
            'email'       => $user->email,
            'password'    => 'Password1!',
            'redirect_to' => '/organizations',
        ])->assertRedirect('/auth/verification');

        $testResponse = $this->get('/auth/verification', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200);
        self::assertSame('Auth/EmailVerification', $testResponse->json()['component'] ?? null);
        self::assertArrayHasKey('resend_available_in', $testResponse->json()['props'] ?? []);
    }

    #[Test]
    public function itKeepsPageSpecificTitleOnVerificationPage(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'title-' . bin2hex(random_bytes(6)) . '@example.test',
            'Title User',
            'Password1!',
        );

        $this->withCsrf()->post('/auth/login', [
            'email'       => $user->email,
            'password'    => 'Password1!',
            'redirect_to' => '/organizations',
        ])->assertRedirect('/auth/verification');

        self::assertStringContainsString('<title>Verify email — ', $this->get('/auth/verification')->assertStatus(200)->body());
    }

    #[Test]
    public function itRendersTheBannedPageForAnUnverifiedBannedUserAfterLogin(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'banned-pending-' . bin2hex(random_bytes(6)) . '@example.test',
            'Banned Pending User',
            'Password1!',
        );
        $admin = $identity->createUser(
            'banned-pending-admin-' . bin2hex(random_bytes(6)) . '@example.test',
            'Banned Pending Admin',
            'Password1!',
            ['admin'],
        );

        $identity->banUser($user->id, $admin->id, 'Violation of project rules.');

        $this->withCsrf()->post('/auth/login', [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertRedirect('/account/banned');

        $this->get('/account/banned', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->assertStatus(200)->assertInertiaComponent('Auth/Banned');
    }

    #[Test]
    public function itGatesAnExistingSessionUntilTheUserIsUnbanned(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'session-banned-' . bin2hex(random_bytes(6)) . '@example.test',
            'Session Banned User',
            'Password1!',
        );
        $admin = $identity->createUser(
            'session-banned-admin-' . bin2hex(random_bytes(6)) . '@example.test',
            'Session Banned Admin',
            'Password1!',
            ['admin'],
        );
        $identity->markUserEmailAsVerified($user->id);

        $this->withCsrf()->post('/auth/login', [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertRedirect('/organizations');

        $identity->banUser($user->id, $admin->id, 'Violation of project rules.');

        $this->get('/profile')->assertRedirect('/account/banned');
        $this->get('/account/banned', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->assertStatus(200)->assertInertiaComponent('Auth/Banned');

        $identity->unbanUser($user->id, $admin->id);

        $this->get('/profile', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ])->assertStatus(200);
    }

    #[Test]
    public function itDoesNotShareOrganizationDataOnTheBannedPage(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'banned-organization-' . bin2hex(random_bytes(6)) . '@example.test',
            'Banned Organization User',
            'Password1!',
        );
        $admin = $identity->createUser(
            'banned-organization-admin-' . bin2hex(random_bytes(6)) . '@example.test',
            'Banned Organization Admin',
            'Password1!',
            ['admin'],
        );
        $identity->markUserEmailAsVerified($user->id);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations    = $this->getService(OrganizationApiFacadeInterface::class);
        $organizationDto  = $organizations->registerOrganization('banned-organization-' . bin2hex(random_bytes(6)), $user->id);

        $this->withCsrf()->post('/auth/login', [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertRedirect('/organizations');

        $identity->banUser($user->id, $admin->id, 'Violation of project rules.');

        $testResponse = $this->get('/account/banned', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Auth/Banned');
        self::assertSame([], $testResponse->json()['props']['organizations'] ?? null);
        self::assertNull($testResponse->json()['props']['organization'] ?? null);
        self::assertNull($testResponse->json()['props']['current_membership'] ?? null);
        self::assertStringNotContainsString($organizationDto->name, (string) json_encode($testResponse->json()));
    }

    #[Test]
    public function itReturnsValidationErrorForMalformedVerificationCode(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'verify-validation-' . bin2hex(random_bytes(6)) . '@example.test',
            'Malformed Verification User',
            'Password1!',
        );

        $this->withCsrf()->post('/auth/login', [
            'email'       => $user->email,
            'password'    => 'Password1!',
            'redirect_to' => '/organizations',
        ])->assertRedirect('/auth/verification');

        $this->withCsrf()->post('/auth/verification/verify', [
            'code' => '12ab',
        ])->assertRedirect('/auth/verification');

        $testResponse = $this->get('/auth/verification', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200);
        self::assertSame('Auth/EmailVerification', $testResponse->json()['component'] ?? null);
        self::assertArrayHasKey('errors', $testResponse->json()['props'] ?? []);
        self::assertArrayHasKey('code', $testResponse->json()['props']['errors'] ?? []);
        self::assertNotSame('', $testResponse->json()['props']['errors']['code'][0] ?? null);
    }

    #[Test]
    public function itVerifiesCodeAndRedirectsToStoredDestination(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'verify-success-' . bin2hex(random_bytes(6)) . '@example.test',
            'Verified User',
            'Password1!',
        );
        $verificationCode = '123456';

        $this->withCsrf()->post('/auth/login', [
            'email'       => $user->email,
            'password'    => 'Password1!',
            'redirect_to' => '/verify-success',
        ])->assertRedirect('/auth/verification');

        $this->seedVerificationCode($user->id, $verificationCode);

        $this->withCsrf()->post('/auth/verification/verify', [
            'code' => $verificationCode,
        ])->assertRedirect('/verify-success');

        $updated = $identity->findUserById($user->id);
        self::assertNotNull($updated);
        self::assertTrue($updated->isEmailVerified);
    }

    private function seedVerificationCode(string $userId, string $code): void
    {
        $repository = $this->getService(EmailVerificationCodeRepositoryInterface::class);
        if (! $repository instanceof EmailVerificationCodeRepositoryInterface) {
            return;
        }

        $record = $repository->findActiveByUserUuid($userId);
        if (! $record instanceof EmailVerificationCode) {
            $record = new EmailVerificationCode(
                userUuid: $userId,
                codeHash: password_hash($code, PASSWORD_DEFAULT),
                expiresAt: new Chronos('+10 minutes'),
            );

            $repository->save($record);

            return;
        }

        $record->setCodeHash(password_hash($code, PASSWORD_DEFAULT));
        $record->setExpiresAt(new Chronos('+10 minutes'));
        $repository->save($record);
    }
}
