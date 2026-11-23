<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use const PASSWORD_DEFAULT;

use Cake\Chronos\Chronos;
use Common\Mail\MailerInterface;
use Packio\Identity\Application\Command\EmailVerificationService;
use Packio\Identity\Application\Command\OAuth\OAuthAccountLinkerInterface;
use Packio\Identity\Application\Command\OAuth\OAuthCallbackTransactionInterface;
use Packio\Identity\Application\Command\OAuth\OAuthCallbackUseCase;
use Packio\Identity\Application\Command\OAuth\OAuthContinuationWorkflow;
use Packio\Identity\Application\Command\OAuth\OAuthRegistrationPolicyInterface;
use Packio\Identity\Application\Command\OAuth\OAuthUserResolverInterface;
use Packio\Identity\Application\Command\OAuth\ResolveOAuthCallbackCommand;
use Packio\Identity\Application\Command\VerificationDispatch;
use Packio\Identity\Application\Mail\AuthEmailTemplateRendererInterface;
use Packio\Identity\Application\Result\OAuthCallbackResult;
use Packio\Identity\Application\Result\OAuthCallbackStatus;
use Packio\Identity\Domain\Entity\EmailVerificationCode;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\EmailVerificationCodeRepositoryInterface;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

use function password_hash;

#[CoversClass(OAuthContinuationWorkflow::class)]
final class OAuthContinuationWorkflowTest extends TestCase
{
    private const string USER_ID = '018f25e5-fd27-7000-8000-000000000010';

    #[Test]
    public function plainVerificationCarriesNoProvider(): void
    {
        $oAuthCallbackResult = $this->workflow($this->user(false))->verify(self::USER_ID, '123456', null, false);

        self::assertSame(OAuthCallbackStatus::Authenticated, $oAuthCallbackResult->status);
        self::assertNull($oAuthCallbackResult->provider);
    }

    #[Test]
    public function userVerifyingAnOAuthContinuationIsAuthenticatedWithTheCommandProvider(): void
    {
        $oAuthCallbackResult = $this->workflow($this->user(false))->verify(self::USER_ID, '123456', $this->command(), true);

        self::assertSame(OAuthCallbackStatus::Authenticated, $oAuthCallbackResult->status);
        self::assertSame(OAuthProvider::Github, $oAuthCallbackResult->provider);
    }

    #[Test]
    public function bannedUserVerifyingAnOAuthContinuationCarriesTheCommandProvider(): void
    {
        $oAuthCallbackResult = $this->workflow($this->user(true))->verify(self::USER_ID, '123456', $this->command(), true);

        self::assertSame(OAuthCallbackStatus::Banned, $oAuthCallbackResult->status);
        self::assertSame(OAuthProvider::Github, $oAuthCallbackResult->provider);
    }

    private function user(bool $banned): User
    {
        $user = new User('verify@example.test', 'Verify');
        $user->setIdentifier(Uuid::fromString(self::USER_ID));
        if ($banned) {
            $user->ban('Violation of project rules.', 'admin');
        }

        return $user;
    }

    private function workflow(User $user): OAuthContinuationWorkflow
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findById')->willReturn($user);

        $emailVerificationCode = new EmailVerificationCode(userUuid: self::USER_ID);
        $emailVerificationCode->setCodeHash(password_hash('123456', PASSWORD_DEFAULT));
        $emailVerificationCode->setExpiresAt(new Chronos('+10 minutes'));
        $codes = $this->createStub(EmailVerificationCodeRepositoryInterface::class);
        $codes->method('findActiveByUserUuid')->willReturn($emailVerificationCode);

        $transaction = $this->createStub(OAuthCallbackTransactionInterface::class);
        $transaction->method('run')->willReturnCallback(static fn (callable $callback): OAuthCallbackResult => $callback());

        return new OAuthContinuationWorkflow(
            new OAuthCallbackUseCase($users, $this->createStub(UserOAuthAccountRepositoryInterface::class), $this->createStub(OAuthRegistrationPolicyInterface::class), $this->createStub(OAuthUserResolverInterface::class), $this->createStub(OAuthAccountLinkerInterface::class), $transaction),
            $users,
            new EmailVerificationService($codes, $users, $this->createStub(MailerInterface::class), $this->createStub(AuthEmailTemplateRendererInterface::class)),
            new VerificationDispatch($this->createStub(EventDispatcherInterface::class), $this->createStub(LoggerInterface::class)),
            $transaction,
        );
    }

    private function command(): ResolveOAuthCallbackCommand
    {
        return new ResolveOAuthCallbackCommand(OAuthProvider::Github, new OAuthTokenResponse('token', null, null), new OAuthUserData('123', null, null, null, null));
    }
}
