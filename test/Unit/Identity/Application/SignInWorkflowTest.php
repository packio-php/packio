<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Application;

use Packio\Identity\Application\Command\IdentityCredentialsInterface;
use Packio\Identity\Application\Command\SignInWorkflow;
use Packio\Identity\Application\Command\VerificationDispatch;
use Packio\Identity\Application\Event\EmailVerificationRequested;
use Packio\Identity\Application\Result\IdentityUserResult;
use Packio\Identity\Application\Result\SignInStatus;
use Packio\Identity\Domain\EmailVerificationReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(SignInWorkflow::class)]
#[CoversClass(VerificationDispatch::class)]
final class SignInWorkflowTest extends TestCase
{
    #[Test]
    public function failedVerificationDeliveryStillRequiresVerificationAndNeverAuthenticates(): void
    {
        $credentials = $this->createMock(IdentityCredentialsInterface::class);
        $credentials->expects(self::once())->method('authenticateCredentials')->with('user@example.test', 'Password1!')->willReturn(new IdentityUserResult('018f25e5-fd27-7000-8000-000000000001', 'user@example.test', 'User', ['user'], '', '', true, false));
        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects(self::once())->method('dispatch')->with(self::callback(static fn (EmailVerificationRequested $emailVerificationRequested): bool => EmailVerificationReason::Login === $emailVerificationRequested->reason && ! $emailVerificationRequested->force))->willThrowException(new RuntimeException('Mail unavailable'));
        $signInResult = new SignInWorkflow($credentials, new VerificationDispatch($events, $this->createStub(LoggerInterface::class)))->handle('user@example.test', 'Password1!');
        self::assertSame(SignInStatus::VerificationRequired, $signInResult->status);
        self::assertSame('018f25e5-fd27-7000-8000-000000000001', $signInResult->userId);
    }
}
