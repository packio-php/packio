<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Security\Session;

use Packio\Application\Security\Session\PendingEmailVerificationSession;
use PackioTest\Support\Fake\InMemorySession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function time;

#[CoversClass(PendingEmailVerificationSession::class)]
final class PendingEmailVerificationSessionTest extends TestCase
{
    private PendingEmailVerificationSession $pendingEmailVerificationSession;

    private InMemorySession $inMemorySession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pendingEmailVerificationSession         = new PendingEmailVerificationSession();
        $this->inMemorySession                         = new InMemorySession();
    }

    #[Test]
    public function registrationUsesIndependentPendingStateAndClearsItOnLoginFlow(): void
    {
        $this->pendingEmailVerificationSession->startRegistration($this->inMemorySession, null, '/organizations');
        self::assertTrue($this->pendingEmailVerificationSession->isDecoy($this->inMemorySession));
        self::assertNotNull($this->pendingEmailVerificationSession->userUuid($this->inMemorySession));
        self::assertSame(120, $this->pendingEmailVerificationSession->registrationDelay($this->inMemorySession));
        $this->inMemorySession->set('registration_resend_at', time() - 121);
        self::assertSame(0, $this->pendingEmailVerificationSession->registrationDelay($this->inMemorySession));
        $this->pendingEmailVerificationSession->markRegistrationDelivery($this->inMemorySession);
        self::assertSame(120, $this->pendingEmailVerificationSession->registrationDelay($this->inMemorySession));
        $this->pendingEmailVerificationSession->start($this->inMemorySession, 'real-user');
        self::assertFalse($this->pendingEmailVerificationSession->isDecoy($this->inMemorySession));
        self::assertNull($this->pendingEmailVerificationSession->registrationDelay($this->inMemorySession));
        $this->pendingEmailVerificationSession->startRegistration($this->inMemorySession, 'new-user');
        self::assertFalse($this->pendingEmailVerificationSession->isDecoy($this->inMemorySession));
        self::assertSame('new-user', $this->pendingEmailVerificationSession->userUuid($this->inMemorySession));
        $this->pendingEmailVerificationSession->clear($this->inMemorySession);
        self::assertSame([], $this->inMemorySession->toArray());
    }

    #[Test]
    public function startStoresUserUuidAndRedirectTarget(): void
    {
        $this->pendingEmailVerificationSession->start($this->inMemorySession, 'user-1', '/organizations');

        self::assertSame('user-1', $this->pendingEmailVerificationSession->userUuid($this->inMemorySession));
        self::assertSame('/organizations', $this->pendingEmailVerificationSession->redirectTarget($this->inMemorySession));
    }

    #[Test]
    public function startWithoutRedirectTargetStoresOnlyUserUuid(): void
    {
        $this->pendingEmailVerificationSession->start($this->inMemorySession, 'user-1');

        self::assertSame('user-1', $this->pendingEmailVerificationSession->userUuid($this->inMemorySession));
        self::assertNull($this->pendingEmailVerificationSession->redirectTarget($this->inMemorySession));
    }

    #[Test]
    public function restartWithNullRedirectTargetRemovesStaleRedirectTarget(): void
    {
        $this->pendingEmailVerificationSession->start($this->inMemorySession, 'user-1', '/organizations');

        $this->pendingEmailVerificationSession->start($this->inMemorySession, 'user-2');

        self::assertSame('user-2', $this->pendingEmailVerificationSession->userUuid($this->inMemorySession));
        self::assertNull($this->pendingEmailVerificationSession->redirectTarget($this->inMemorySession));
        self::assertArrayNotHasKey('pending_verification_redirect_to', $this->inMemorySession->toArray());
    }

    #[Test]
    public function restartWithEmptyStringRedirectTargetRemovesStaleRedirectTarget(): void
    {
        $this->pendingEmailVerificationSession->start($this->inMemorySession, 'user-1', '/organizations');

        $this->pendingEmailVerificationSession->start($this->inMemorySession, 'user-2', '');

        self::assertSame('user-2', $this->pendingEmailVerificationSession->userUuid($this->inMemorySession));
        self::assertNull($this->pendingEmailVerificationSession->redirectTarget($this->inMemorySession));
    }

    #[Test]
    public function restartReplacesPreviousUserUuidAndRedirectTarget(): void
    {
        $this->pendingEmailVerificationSession->start($this->inMemorySession, 'user-1', '/organizations');

        $this->pendingEmailVerificationSession->start($this->inMemorySession, 'user-2', '/dashboard');

        self::assertSame('user-2', $this->pendingEmailVerificationSession->userUuid($this->inMemorySession));
        self::assertSame('/dashboard', $this->pendingEmailVerificationSession->redirectTarget($this->inMemorySession));
    }

    #[Test]
    public function userUuidReturnsNullForEmptyOrNonStringStoredValues(): void
    {
        $this->inMemorySession->set('pending_verification_user_uuid', '');

        self::assertNull($this->pendingEmailVerificationSession->userUuid($this->inMemorySession));

        $this->inMemorySession->set('pending_verification_user_uuid', 42);

        self::assertNull($this->pendingEmailVerificationSession->userUuid($this->inMemorySession));
    }

    #[Test]
    public function userUuidAndRedirectTargetReturnNullOnEmptySession(): void
    {
        self::assertNull($this->pendingEmailVerificationSession->userUuid($this->inMemorySession));
        self::assertNull($this->pendingEmailVerificationSession->redirectTarget($this->inMemorySession));
    }

    #[Test]
    public function redirectTargetReturnsNullForEmptyOrNonStringStoredValues(): void
    {
        $this->inMemorySession->set('pending_verification_redirect_to', '');

        self::assertNull($this->pendingEmailVerificationSession->redirectTarget($this->inMemorySession));

        $this->inMemorySession->set('pending_verification_redirect_to', ['array']);

        self::assertNull($this->pendingEmailVerificationSession->redirectTarget($this->inMemorySession));
    }

    #[Test]
    public function clearRemovesBothKeys(): void
    {
        $this->pendingEmailVerificationSession->start($this->inMemorySession, 'user-1', '/organizations');

        $this->pendingEmailVerificationSession->clear($this->inMemorySession);

        self::assertNull($this->pendingEmailVerificationSession->userUuid($this->inMemorySession));
        self::assertNull($this->pendingEmailVerificationSession->redirectTarget($this->inMemorySession));
        self::assertFalse($this->inMemorySession->has('pending_verification_user_uuid'));
        self::assertFalse($this->inMemorySession->has('pending_verification_redirect_to'));
    }

    #[Test]
    public function clearIsSafeOnEmptySession(): void
    {
        $this->pendingEmailVerificationSession->clear($this->inMemorySession);

        self::assertSame([], $this->inMemorySession->toArray());
    }
}
