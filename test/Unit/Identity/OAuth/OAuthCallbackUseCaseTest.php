<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use Packio\Identity\Application\Command\OAuth\OAuthAccountLinkerInterface;
use Packio\Identity\Application\Command\OAuth\OAuthCallbackTransactionInterface;
use Packio\Identity\Application\Command\OAuth\OAuthCallbackUseCase;
use Packio\Identity\Application\Command\OAuth\OAuthRegistrationPolicyInterface;
use Packio\Identity\Application\Command\OAuth\OAuthUserResolverInterface;
use Packio\Identity\Application\Command\OAuth\ResolveOAuthCallbackCommand;
use Packio\Identity\Application\Result\OAuthCallbackResult;
use Packio\Identity\Application\Result\OAuthCallbackStatus;
use Packio\Identity\Application\Result\OAuthUserResolution;
use Packio\Identity\Application\Result\OAuthUserResolutionType;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

#[CoversClass(OAuthCallbackUseCase::class)]
#[AllowMockObjectsWithoutExpectations]
final class OAuthCallbackUseCaseTest extends TestCase
{
    #[Test]
    public function closedRegistrationReturnsTypedOutcomeBeforeCreation(): void
    {
        $oAuthCallbackResult = $this->useCase()->handle($this->command());
        self::assertSame(OAuthCallbackStatus::RegistrationDisabled, $oAuthCallbackResult->status);
        self::assertFalse($oAuthCallbackResult->userCreated);
        self::assertNull($oAuthCallbackResult->userId);
    }

    #[Test]
    public function unknownCurrentUserFailsWithoutResolvingAnotherIdentity(): void
    {
        $this->expectExceptionMessage('Current user not found');
        $this->useCase()->handle($this->command(currentUserId: 'missing'));
    }

    #[Test]
    public function manualEmailCannotTakeOverExistingUser(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('findByEmail')->with('existing@example.test')->willReturn(new User('existing@example.test', 'Existing'));
        $this->expectExceptionMessage('An account with this email already exists.');
        $this->useCase($users)->handle($this->command(email: ' existing@example.test '));
    }

    #[Test]
    #[DataProvider('blankEmails')]
    public function blankManualEmailIsRejected(string $email): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('findByEmail');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Enter a valid email address.');
        $this->useCase($users)->handle($this->command(email: $email));
    }

    /** @return iterable<string, array{string}> */
    public static function blankEmails(): iterable
    {
        yield 'empty' => [''];

        yield 'whitespace' => [" \t\n"];
    }

    #[Test]
    public function bannedUserCannotBeVerifiedOrLinkedByTheCallback(): void
    {
        $user = new User('banned@example.test', 'Banned');
        $user->setIdentifier(Uuid::fromString('018f25e5-fd27-7000-8000-000000000001'));
        $user->ban('Violation of project rules.', 'admin');
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('findById')->willReturn($user);
        $users->expects(self::never())->method('save');
        $oAuthCallbackResult = $this->useCase($users)->handle($this->command(currentUserId: $user->getIdentifier()->toString()));
        self::assertSame(OAuthCallbackStatus::Banned, $oAuthCallbackResult->status);
        self::assertSame($user->getIdentifier()->toString(), $oAuthCallbackResult->userId);
        self::assertFalse($user->isEmailVerified());
    }

    #[Test]
    public function missingProviderEmailAsksForEmailBeforeCheckingRegistration(): void
    {
        $policy = $this->createMock(OAuthRegistrationPolicyInterface::class);
        $policy->expects(self::never())->method('allowsRegistration');
        $oAuthCallbackResult = $this->useCase(oAuthRegistrationPolicy: $policy)->handle($this->command(providerEmail: null));
        self::assertSame(OAuthCallbackStatus::EmailRequired, $oAuthCallbackResult->status);
        self::assertNull($oAuthCallbackResult->userId);
    }

    #[Test]
    public function invitationRedirectReachesRegistrationPolicyAndCreatesVerifiedLinkedUser(): void
    {
        $user = new User('provider@example.test', 'Invited');
        $user->setIdentifier(Uuid::fromString('018f25e5-fd27-7000-8000-000000000002'));
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('save')->with($user)->willReturn($user);
        $policy = $this->createMock(OAuthRegistrationPolicyInterface::class);
        $policy->expects(self::once())->method('allowsRegistration')->with(null, self::anything(), '/organization/invite/orgi_pending')->willReturn(true);
        $resolver = $this->createMock(OAuthUserResolverInterface::class);
        $resolver->expects(self::once())->method('resolveUser')->willReturn(new OAuthUserResolution(OAuthUserResolutionType::Created, $user));
        $linker = $this->createMock(OAuthAccountLinkerInterface::class);
        $linker->expects(self::once())->method('persistOAuthAccount')->with($user->getIdentifier()->toString());
        $oAuthCallbackResult = $this->useCase($users, $policy, $resolver, $linker)->handle($this->command(registrationRedirectTarget: '/organization/invite/orgi_pending'));
        self::assertSame(OAuthCallbackStatus::Authenticated, $oAuthCallbackResult->status);
        self::assertTrue($oAuthCallbackResult->userCreated);
        self::assertSame($user->getIdentifier()->toString(), $oAuthCallbackResult->userId);
        self::assertTrue($user->isEmailVerified());
    }

    #[Test]
    public function linkedAccountWithoutProviderEmailLogsInExistingUser(): void
    {
        $user = new User('existing@example.test', 'Existing');
        $user->setIdentifier(Uuid::fromString('018f25e5-fd27-7000-8000-000000000003'));
        $user->markEmailAsVerified();
        $userOAuthAccount  = UserOAuthAccount::register($user->getIdentifier()->toString(), OAuthProvider::Github, '123', null, null);
        $accounts          = $this->createMock(UserOAuthAccountRepositoryInterface::class);
        $accounts->expects(self::once())->method('findByProviderAndProviderUserId')->with(OAuthProvider::Github, '123')->willReturn($userOAuthAccount);
        $policy = $this->createMock(OAuthRegistrationPolicyInterface::class);
        $policy->expects(self::once())->method('allowsRegistration')->willReturn(true);
        $resolver = $this->createMock(OAuthUserResolverInterface::class);
        $resolver->expects(self::once())->method('resolveUser')->with($userOAuthAccount)->willReturn(new OAuthUserResolution(OAuthUserResolutionType::Linked, $user));
        $linker = $this->createMock(OAuthAccountLinkerInterface::class);
        $linker->expects(self::once())->method('persistOAuthAccount');
        $oAuthCallbackResult = $this->useCase(oAuthRegistrationPolicy: $policy, oAuthUserResolver: $resolver, oAuthAccountLinker: $linker, userOAuthAccountRepository: $accounts)->handle($this->command(providerEmail: null));
        self::assertSame(OAuthCallbackStatus::Authenticated, $oAuthCallbackResult->status);
        self::assertFalse($oAuthCallbackResult->userCreated);
        self::assertSame($user->getIdentifier()->toString(), $oAuthCallbackResult->userId);
    }

    private function useCase(
        ?UserRepositoryInterface $userRepository = null,
        ?OAuthRegistrationPolicyInterface $oAuthRegistrationPolicy = null,
        ?OAuthUserResolverInterface $oAuthUserResolver = null,
        ?OAuthAccountLinkerInterface $oAuthAccountLinker = null,
        ?UserOAuthAccountRepositoryInterface $userOAuthAccountRepository = null,
    ): OAuthCallbackUseCase {
        $transaction = $this->createMock(OAuthCallbackTransactionInterface::class);
        $transaction->expects(self::once())->method('run')->willReturnCallback(static fn (callable $callback): OAuthCallbackResult => $callback());
        if (! $oAuthUserResolver instanceof OAuthUserResolverInterface) {
            $oAuthUserResolver = $this->createMock(OAuthUserResolverInterface::class);
            $oAuthUserResolver->expects(self::never())->method('resolveUser');
        }

        if (! $oAuthAccountLinker instanceof OAuthAccountLinkerInterface) {
            $oAuthAccountLinker = $this->createMock(OAuthAccountLinkerInterface::class);
            $oAuthAccountLinker->expects(self::never())->method('persistOAuthAccount');
        }

        return new OAuthCallbackUseCase($userRepository ?? $this->createMock(UserRepositoryInterface::class), $userOAuthAccountRepository ?? $this->createMock(UserOAuthAccountRepositoryInterface::class), $oAuthRegistrationPolicy ?? $this->createMock(OAuthRegistrationPolicyInterface::class), $oAuthUserResolver, $oAuthAccountLinker, $transaction);
    }

    private function command(
        ?string $email = null,
        ?string $currentUserId = null,
        ?string $providerEmail = 'provider@example.test',
        string $registrationRedirectTarget = ''
    ): ResolveOAuthCallbackCommand {
        return new ResolveOAuthCallbackCommand(OAuthProvider::Github, new OAuthTokenResponse('token', null, null), new OAuthUserData('123', $providerEmail, null, null, null), currentUserId: $currentUserId, registrationRedirectTarget: $registrationRedirectTarget, email: $email);
    }
}
