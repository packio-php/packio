<?php

declare(strict_types=1);

namespace PackioTest\Feature\Profile;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\IdentityCommandService;
use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

use function bin2hex;
use function random_bytes;

final class ProfileOAuthDisconnectFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function itDisconnectsOwnOAuthAccountFromProfile(): void
    {
        $userDto          = $this->createPasswordUser();
        $userOAuthAccount = $this->createOAuthAccount($userDto->id, OAuthProvider::Github, 'github-own');

        $this->logIn($userDto);

        $this->post('/profile/oauth/' . $userOAuthAccount->getIdentifier()->toString() . '/disconnect')
            ->assertRedirect('/profile')
        ;

        self::assertNull($this->oauthAccounts()->findById($userOAuthAccount->getIdentifier()->toString()));

        $testResponse = $this->get('/profile', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        self::assertSame('success', $testResponse->json()['flash']['toasts'][0]['type'] ?? null);
        self::assertSame(
            'OAuth sign-in method disconnected.',
            $testResponse->json()['flash']['toasts'][0]['message'] ?? null,
        );
    }

    #[Test]
    public function itRejectsDisconnectingAnotherUsersOAuthAccount(): void
    {
        $userDto          = $this->createPasswordUser();
        $otherUser        = $this->createPasswordUser();
        $userOAuthAccount = $this->createOAuthAccount($otherUser->id, OAuthProvider::Github, 'github-foreign');

        $this->logIn($userDto);

        $this->post('/profile/oauth/' . $userOAuthAccount->getIdentifier()->toString() . '/disconnect')
            ->assertRedirect('/profile')
        ;

        self::assertNotNull($this->oauthAccounts()->findById($userOAuthAccount->getIdentifier()->toString()));

        $testResponse = $this->get('/profile', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        self::assertSame('error', $testResponse->json()['flash']['toasts'][0]['type'] ?? null);
        self::assertSame(
            'You can only disconnect your own OAuth accounts.',
            $testResponse->json()['flash']['toasts'][0]['message'] ?? null,
        );
    }

    #[Test]
    public function itKeepsOnlySignInMethodForOAuthOnlyUser(): void
    {
        $user = $this->getService(IdentityCommandService::class)->createUserWithoutPassword(
            'oauth-only-' . bin2hex(random_bytes(6)) . '@example.test',
            'OAuth Only User',
        );
        $userOAuthAccount = $this->createOAuthAccount($user->getIdentifier()->toString(), OAuthProvider::Github, 'github-only');

        $result = $this->identity()->disconnectOAuthAccount($user->getIdentifier()->toString(), $userOAuthAccount->getIdentifier()->toString());

        self::assertFalse($result['success']);
        self::assertSame(
            'Set a password or connect another provider before disconnecting your only sign-in method.',
            $result['message'] ?? null,
        );
        self::assertNotNull($this->oauthAccounts()->findById($userOAuthAccount->getIdentifier()->toString()));
    }

    private function createPasswordUser(): UserDto
    {
        $userDto = $this->identity()->createUser(
            'profile-oauth-' . bin2hex(random_bytes(6)) . '@example.test',
            'Profile OAuth User',
            self::PASSWORD,
        );
        $this->identity()->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function createOAuthAccount(string $userId, OAuthProvider $oAuthProvider, string $providerUserId): UserOAuthAccount
    {
        $userOAuthAccount = UserOAuthAccount::register(
            userId: $userId,
            oAuthProvider: $oAuthProvider,
            providerUserId: $providerUserId . '-' . bin2hex(random_bytes(4)),
            providerEmail: $providerUserId . '@example.test',
            providerUsername: $providerUserId,
        );

        $this->oauthAccounts()->save($userOAuthAccount);

        return $userOAuthAccount;
    }

    private function logIn(UserDto $userDto): void
    {
        $this->withCsrf()->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');
    }

    private function identity(): IdentityApiFacadeInterface
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        if (! $identity instanceof IdentityApiFacadeInterface) {
            throw new RuntimeException('Identity API facade is not configured.');
        }

        return $identity;
    }

    private function oauthAccounts(): UserOAuthAccountRepositoryInterface
    {
        $oauthAccounts = $this->getService(UserOAuthAccountRepositoryInterface::class);
        if (! $oauthAccounts instanceof UserOAuthAccountRepositoryInterface) {
            throw new RuntimeException('User OAuth account repository is not configured.');
        }

        return $oauthAccounts;
    }
}
