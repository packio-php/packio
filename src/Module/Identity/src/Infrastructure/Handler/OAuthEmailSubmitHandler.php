<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use InvalidArgumentException;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Session\SessionInterface;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Application\Service\SafeRedirectTarget;
use Packio\Identity\Application\Command\OAuth\OAuthContinuationInterface;
use Packio\Identity\Application\Command\OAuth\ResolveOAuthCallbackCommand;
use Packio\Identity\Application\Result\OAuthCallbackStatus;
use Packio\Identity\Infrastructure\Dto\PendingOAuthEmailContext;
use Packio\Identity\Infrastructure\Request\OAuthEmailRequest;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Authentication\Attribute\GuestOnly;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/auth/oauth/email', name: 'auth.oauth.email.submit')]
#[GuestOnly]
#[RateLimit(
    key: 'oauth_email',
    by: ['ip'],
    response: 'redirect',
    message: 'Too many attempts. Please try again later.',
    redirectTo: '/auth/oauth/email',
    notify: 'none'
)]
#[MapRequest(body: OAuthEmailRequest::class)]
#[ValidateMappedRequest(OAuthEmailRequest::class)]
#[ValidationFailed(
    redirectTo: '/auth/oauth/email',
    message: 'Invalid request data.',
    notify: ValidationNotify::None,
    oldInput: ['email'],
)]
final readonly class OAuthEmailSubmitHandler implements RequestHandlerInterface
{
    public function __construct(
        private OAuthSessionContext $oAuthSessionContext,
        private OAuthContinuationInterface $oAuthContinuation,
        private OAuthOutcomePresenter $oAuthOutcomePresenter,
        private SafeRedirectTarget $safeRedirectTarget,
        private TurnstileRequestGuard $turnstileRequestGuard,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private PendingEmailVerificationSession $pendingEmailVerificationSession = new PendingEmailVerificationSession(),
        private ValidationRedirectState $validationRedirectState = new ValidationRedirectState()
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $session = $request->getAttribute('session');
        $context = $this->oAuthSessionContext->pendingEmailContext($session);
        if (! $context instanceof PendingOAuthEmailContext) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'OAuth session expired. Please try again.');

            return new RedirectResponse('/auth/login');
        }

        if ($session instanceof SessionInterface && null !== $this->pendingEmailVerificationSession->userUuid($session)) {
            return new RedirectResponse('/auth/verification');
        }

        /** @var OAuthEmailRequest $form */
        $form = $request->getAttribute(OAuthEmailRequest::class);

        if (! $this->turnstileRequestGuard->verify($request)) {
            $this->validationRedirectState->errors($request, $this->turnstileRequestGuard->error());
            $this->validationRedirectState->old($request, [
                'email' => $form->email,
            ]);

            return new RedirectResponse('/auth/oauth/email');
        }

        $command = $context->command($form->email);
        if (! $command instanceof ResolveOAuthCallbackCommand) {
            $this->oAuthSessionContext->consumePendingEmailContext($session);
            $this->inertiaNotificationMessenger->toast($request, 'error', 'OAuth session expired. Please try again.');

            return new RedirectResponse('/auth/login');
        }

        try {
            $result = $this->oAuthContinuation->submit($command);
        } catch (InvalidArgumentException|RuntimeException) {
            $this->validationRedirectState->errors($request, [
                'email' => ['Unable to complete OAuth sign-in. Please try again.'],
            ]);
            $this->validationRedirectState->old($request, [
                'email' => $form->email,
            ]);

            return new RedirectResponse('/auth/oauth/email');
        }

        if (OAuthCallbackStatus::VerificationRequired === $result->status) {
            $this->oAuthSessionContext->requireContinuation($session);
        }

        return $this->oAuthOutcomePresenter->present($request, $result, $this->safeRedirectTarget->normalize($context->registrationRedirectTarget));
    }
}
