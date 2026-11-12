<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Session\SessionInterface;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Identity\Api\EmailVerificationReasonType;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Throwable;

#[Post('/auth/verification/resend', name: 'auth.verification.resend')]
#[RateLimit(key: 'email_verification_resend', by: ['ip'], response: 'redirect', message: 'Too many resend requests. Please try again later.', redirectTo: '/auth/verification', notify: 'flash')]
final readonly class EmailVerificationResendHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private TurnstileRequestGuard $turnstileRequestGuard,
        private PendingEmailVerificationSession $pendingEmailVerificationSession = new PendingEmailVerificationSession(),
        private ValidationRedirectState $validationRedirectState = new ValidationRedirectState(),
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userUuid = $this->getUserUuidFromSession($request);
        if (null === $userUuid) {
            return new RedirectResponse('/auth/login');
        }

        if (! $this->turnstileRequestGuard->verify($request)) {
            $this->validationRedirectState->errors($request, $this->turnstileRequestGuard->error());

            return new RedirectResponse('/auth/verification');
        }

        $session           = $request->getAttribute('session');
        $registrationDelay = $this->pendingEmailVerificationSession->registrationDelay($session);
        $retryAfter        = $registrationDelay ?? $this->identityApiFacade->emailVerificationRemainingResendDelay($userUuid);
        if ($retryAfter > 0) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Please wait before requesting a new code.');

            return new RedirectResponse('/auth/verification');
        }

        try {
            $this->identityApiFacade->requestEmailVerification($userUuid, true, EmailVerificationReasonType::ManualResend);
        } catch (Throwable) {
            if (null === $registrationDelay) {
                $this->inertiaNotificationMessenger->flash($request, 'error', 'Unable to send verification email right now. Please try again.');

                return new RedirectResponse('/auth/verification');
            }
        }

        if (null !== $registrationDelay) {
            $this->pendingEmailVerificationSession->markRegistrationDelivery($session);
        }

        $this->inertiaNotificationMessenger->flash($request, 'success', 'Verification code sent.');

        return new RedirectResponse('/auth/verification');
    }

    private function getUserUuidFromSession(ServerRequestInterface $serverRequest): ?string
    {
        $session = $serverRequest->getAttribute('session');
        if (! $session instanceof SessionInterface) {
            return null;
        }

        return $this->pendingEmailVerificationSession->userUuid($session);
    }
}
