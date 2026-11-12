<?php

declare(strict_types=1);

namespace PackioTest\Integration\Identity\Infrastructure\OAuth;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Identity\Application\Command\OAuth\OAuthAccountLinkerInterface;
use Packio\Identity\Application\Command\OAuth\OAuthCallbackUseCase;
use Packio\Identity\Application\Command\OAuth\ResolveOAuthCallbackCommand;
use Packio\Identity\Application\Result\OAuthCallbackStatus;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use Packio\Identity\Infrastructure\OAuth\OAuthCallbackTransaction;
use Packio\Identity\Infrastructure\OAuth\OAuthRegistrationPolicy;
use Packio\Identity\Infrastructure\OAuth\OAuthUserResolver;
use PackioTest\Support\Base\IntegrationTestCase;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

use function array_intersect;
use function array_values;
use function password_hash;

final class OAuthCallbackUseCaseTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        $settings = $this->createStub(ProjectSettingsApiFacadeInterface::class);
        $settings->method('registrationOpen')->willReturn(true);
        $this->serviceOverrides[ProjectSettingsApiFacadeInterface::class] = $settings;
        parent::setUp();
    }

    #[Test]
    public function createsVerifiedUserAndEncryptedAccountInOneTransaction(): void
    {
        $oAuthCallbackResult = $this->useCase()->handle($this->command());
        self::assertSame(OAuthCallbackStatus::Authenticated, $oAuthCallbackResult->status);
        self::assertTrue($oAuthCallbackResult->userCreated);
        $user = $this->users()->findByEmail('oauth@example.test');
        self::assertNotNull($user);
        self::assertTrue($user->isEmailVerified());
        $account = $this->accounts()->findByProviderAndProviderUserId(OAuthProvider::Github, '123');
        self::assertNotNull($account);
        self::assertSame($oAuthCallbackResult->userId, $account->userId());
        self::assertSame(['repo'], $account->scopes());
        $columns = $this->getService('dbal')->database()->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'user_oauth_accounts'")->fetchAll(PDO::FETCH_COLUMN);
        self::assertNotEmpty($columns);
        self::assertSame([], array_values(array_intersect(['access_token', 'refresh_token', 'token_expires_at'], $columns)));
        $again = $this->useCase()->handle($this->command());
        self::assertFalse($again->userCreated);
        self::assertSame($oAuthCallbackResult->userId, $again->userId);
    }

    #[Test]
    public function manualEmailWaitsForVerificationBeforeLinking(): void
    {
        $oAuthUserData       = new OAuthUserData('123', null, 'login', 'Name', null);
        $oAuthTokenResponse  = new OAuthTokenResponse('access-token', null, null);
        $oAuthCallbackResult = $this->useCase()->handle(new ResolveOAuthCallbackCommand(OAuthProvider::Github, $oAuthTokenResponse, $oAuthUserData, email: ' oauth@example.test '));
        self::assertSame(OAuthCallbackStatus::VerificationRequired, $oAuthCallbackResult->status);
        self::assertTrue($oAuthCallbackResult->userCreated);
        self::assertNotNull($oAuthCallbackResult->userId);
        $user = $this->users()->findById($oAuthCallbackResult->userId);
        self::assertNotNull($user);
        self::assertFalse($user->isEmailVerified());
        self::assertNull($this->accounts()->findByProviderAndProviderUserId(OAuthProvider::Github, '123'));
        $user->markEmailAsVerified();
        $this->users()->save($user);
        $verified = $this->useCase()->handle(new ResolveOAuthCallbackCommand(OAuthProvider::Github, $oAuthTokenResponse, $oAuthUserData, currentUserId: $oAuthCallbackResult->userId, verifyEmailFromProvider: false));
        self::assertSame(OAuthCallbackStatus::Authenticated, $verified->status);
        self::assertFalse($verified->userCreated);
        self::assertNotNull($this->accounts()->findByProviderAndProviderUserId(OAuthProvider::Github, '123'));
    }

    #[Test]
    public function missingEmailReturnsTypedOutcomeWithoutPersistence(): void
    {
        $oAuthCallbackResult = $this->useCase()->handle(new ResolveOAuthCallbackCommand(OAuthProvider::Github, new OAuthTokenResponse('token', null, null), new OAuthUserData('123', null, null, null, null)));
        self::assertSame(OAuthCallbackStatus::EmailRequired, $oAuthCallbackResult->status);
        self::assertNull($oAuthCallbackResult->userId);
        self::assertFalse($oAuthCallbackResult->userCreated);
        self::assertNull($this->accounts()->findByProviderAndProviderUserId(OAuthProvider::Github, '123'));
    }

    #[Test]
    public function failedLinkRollsBackNewUserAndAccount(): void
    {
        $this->assertRollback(false);
    }

    #[Test]
    public function failedLinkRollsBackEmailVerificationOfExistingUser(): void
    {
        $this->users()->save(User::register('oauth@example.test', 'Name', '', ['user']));
        $this->assertRollback(true);
    }

    #[Test]
    public function unverifiedPasswordAccountLosesPasswordWhenLinkedByOAuth(): void
    {
        $owner               = $this->users()->save(User::register('oauth@example.test', 'Squatter', password_hash('Secret#123', PASSWORD_DEFAULT), ['user']));
        $oAuthCallbackResult = $this->useCase()->handle($this->command());
        self::assertSame(OAuthCallbackStatus::Authenticated, $oAuthCallbackResult->status);
        self::assertSame($owner->getIdentifier()->toString(), $oAuthCallbackResult->userId);
        $user = $this->users()->findById($owner->getIdentifier()->toString());
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isEmailVerified());
        self::assertSame('', $user->passwordHash());
    }

    #[Test]
    public function verifiedPasswordAccountKeepsPasswordWhenLinkedByOAuth(): void
    {
        $user = User::register('oauth@example.test', 'Owner', password_hash('Secret#123', PASSWORD_DEFAULT), ['user']);
        $user->markEmailAsVerified();
        $hash = $this->users()->save($user)->passwordHash();
        $this->useCase()->handle($this->command());
        $after = $this->users()->findByEmail('oauth@example.test');
        self::assertInstanceOf(User::class, $after);
        self::assertSame($hash, $after->passwordHash());
    }

    private function assertRollback(bool $existing): void
    {
        $accounts = $this->accounts();
        $linker   = $this->createMock(OAuthAccountLinkerInterface::class);
        $linker->expects(self::once())->method('persistOAuthAccount')->willReturnCallback(static function(string $userId) use ($accounts): never {
            $accounts->save(UserOAuthAccount::register($userId, OAuthProvider::Github, '123', null, null));

            throw new RuntimeException('Link failed');
        });
        $oAuthCallbackUseCase = new OAuthCallbackUseCase($this->users(), $accounts, $this->container->get(OAuthRegistrationPolicy::class), $this->container->get(OAuthUserResolver::class), $linker, $this->container->get(OAuthCallbackTransaction::class));

        try {
            $oAuthCallbackUseCase->handle($this->command());
            self::fail('Expected link failure');
        } catch (RuntimeException $exception) {
            self::assertSame('Link failed', $exception->getMessage());
        }
        $user = $this->users()->findByEmail('oauth@example.test');
        if ($existing) {
            self::assertNotNull($user);
            self::assertFalse($user->isEmailVerified());
        } else {
            self::assertNull($user);
        }
        self::assertNull($accounts->findByProviderAndProviderUserId(OAuthProvider::Github, '123'));
    }

    private function command(): ResolveOAuthCallbackCommand
    {
        return new ResolveOAuthCallbackCommand(OAuthProvider::Github, new OAuthTokenResponse('access-token', 'refresh-token', 3600), new OAuthUserData('123', 'oauth@example.test', 'login', 'Name', null), ['repo']);
    }

    private function useCase(): OAuthCallbackUseCase
    {
        return $this->container->get(OAuthCallbackUseCase::class);
    }

    private function users(): UserRepositoryInterface
    {
        return $this->container->get(UserRepositoryInterface::class);
    }

    private function accounts(): UserOAuthAccountRepositoryInterface
    {
        return $this->container->get(UserOAuthAccountRepositoryInterface::class);
    }
}
