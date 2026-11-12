<?php

declare(strict_types=1);

namespace Packio\Application\Security\Session;

use Mezzio\Session\SessionInterface;
use Ramsey\Uuid\Uuid;

use function is_int;
use function is_string;
use function max;
use function time;

/**
 * Owns the pending email verification state stored in the session.
 *
 * All handlers and middleware participating in the email verification flow must
 * read and write this state through this gateway so the session contract stays
 * in a single place.
 */
final readonly class PendingEmailVerificationSession
{
    private const string USER_UUID_KEY = 'pending_verification_user_uuid';

    private const string REDIRECT_TARGET_KEY = 'pending_verification_redirect_to';

    public function start(SessionInterface $session, string $userUuid, ?string $redirectTarget = null): void
    {
        $session->unset('registration_resend_at');
        $session->unset('registration_decoy');
        $session->set(self::USER_UUID_KEY, $userUuid);

        if (null !== $redirectTarget && '' !== $redirectTarget) {
            $session->set(self::REDIRECT_TARGET_KEY, $redirectTarget);

            return;
        }

        $session->unset(self::REDIRECT_TARGET_KEY);
    }

    public function startRegistration(SessionInterface $session, ?string $userUuid, ?string $redirectTarget = null): void
    {
        $this->start($session, $userUuid ?? Uuid::uuid4()->toString(), $redirectTarget);
        $session->set('registration_decoy', null === $userUuid);
        $this->markRegistrationDelivery($session);
    }

    public function registrationDelay(SessionInterface $session): ?int
    {
        $sentAt = $session->get('registration_resend_at');

        return is_int($sentAt) ? max(0, 120 - (time() - $sentAt)) : null;
    }

    public function markRegistrationDelivery(SessionInterface $session): void
    {
        $session->set('registration_resend_at', time());
    }

    public function isDecoy(SessionInterface $session): bool
    {
        return true === $session->get('registration_decoy');
    }

    public function userUuid(SessionInterface $session): ?string
    {
        $userUuid = $session->get(self::USER_UUID_KEY);

        return is_string($userUuid) && '' !== $userUuid ? $userUuid : null;
    }

    public function redirectTarget(SessionInterface $session): ?string
    {
        $redirectTarget = $session->get(self::REDIRECT_TARGET_KEY);

        return is_string($redirectTarget) && '' !== $redirectTarget ? $redirectTarget : null;
    }

    public function clear(SessionInterface $session): void
    {
        $session->unset('registration_resend_at');
        $session->unset('registration_decoy');
        $session->unset(self::USER_UUID_KEY);
        $session->unset(self::REDIRECT_TARGET_KEY);
    }
}
