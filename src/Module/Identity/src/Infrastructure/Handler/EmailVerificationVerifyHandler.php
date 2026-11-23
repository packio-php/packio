<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Session\SessionInterface;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Application\Service\SafeRedirectTarget;
use Packio\Identity\Application\Command\OAuth\OAuthContinuationInterface;
use Packio\Identity\Application\Result\OAuthCallbackStatus;
use Packio\Identity\Infrastructure\Request\EmailVerificationCodeRequest;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/auth/verification/verify', name: 'auth.verification.verify')]
#[RateLimit(
    key: 'email_verification_verify',
    by: ['ip'],
    response: 'redirect',
    message: 'Too many attempts. Please try again later.',
    redirectTo: '/auth/verification',
    notify: 'flash'
)]
#[MapRequest(body: EmailVerificationCodeRequest::class)]
#[ValidateMappedRequest(EmailVerificationCodeRequest::class)]
#[ValidationFailed(redirectTo: '/auth/verification', message: 'Invalid verification code.')]
final readonly class EmailVerificationVerifyHandler implements RequestHandlerInterface
{
    public function __construct(
        private OAuthContinuationInterface $oAuthContinuation,
        private OAuthOutcomePresenter $oAuthOutcomePresenter,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private SafeRedirectTarget $safeRedirectTarget,
        private TurnstileRequestGuard $turnstileRequestGuard,
        private OAuthSessionContext $oAuthSessionContext,
        private PendingEmailVerificationSession $pendingEmailVerificationSession = new PendingEmailVerificationSession(),
        private ValidationRedirectState $validationRedirectState = new ValidationRedirectState(),
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userUuid = $this->getUserUuidFromSession($request);
        if (null === $userUuid) {
            return new RedirectResponse('/auth/login');
        }

        /** @var EmailVerificationCodeRequest $form */
        $form = $request->getAttribute(EmailVerificationCodeRequest::class);
        $code = $form->code;

        if (! $this->turnstileRequestGuard->verify($request)) {
            $this->validationRedirectState->errors($request, $this->turnstileRequestGuard->error());

            return new RedirectResponse('/auth/verification');
        }

        $session = $request->getAttribute('session');
        $pending = $this->oAuthSessionContext->pendingEmailContext($session);

        try {
            $result = $this->oAuthContinuation->verify($userUuid, $code, $pending?->command(), $this->oAuthSessionContext->continuationRequired($session));
        } catch (RuntimeException) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'Unable to complete OAuth sign-in. Please try again.');

            return new RedirectResponse('/auth/verification');
        }

        if (OAuthCallbackStatus::InvalidCode === $result->status) {
            $this->validationRedirectState->errors($request, [
                'code' => ['Invalid code.'],
            ]);

            return new RedirectResponse('/auth/verification');
        }

        $session        = $request->getAttribute('session');
        $redirectTo     = '/organizations';
        $storedRedirect = $this->pendingEmailVerificationSession->redirectTarget($session);
        if (null !== $storedRedirect) {
            $redirectTo = $this->safeRedirectTarget->normalize($storedRedirect) ?: $redirectTo;
        }

        if (OAuthCallbackStatus::Authenticated === $result->status) {
            $this->inertiaNotificationMessenger->toast($request, 'success', 'Email verified.');
        }

        return $this->oAuthOutcomePresenter->present($request, $result, $redirectTo);
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
