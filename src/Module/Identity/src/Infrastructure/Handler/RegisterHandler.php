<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Session\SessionInterface;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Application\Service\SafeRedirectTarget;
use Packio\Identity\Application\Command\RegistrationWorkflow;
use Packio\Identity\Application\Result\RegistrationStatus;
use Packio\Identity\Infrastructure\Request\RegisterRequest;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use Sirix\Mezzio\Authentication\Attribute\GuestOnly;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/auth/register', name: 'auth.session.register')]
#[GuestOnly]
#[RateLimit(
    key: 'register',
    by: ['ip'],
    response: 'redirect',
    message: 'Too many registration attempts. Please try again later.',
    redirectTo: '/auth/register',
    notify: 'none'
)]
#[MapRequest(body: RegisterRequest::class)]
#[ValidateMappedRequest(RegisterRequest::class)]
#[ValidationFailed(
    redirectTo: '/auth/register',
    message: 'Invalid request data.',
    notify: ValidationNotify::None,
    oldInput: ['display_name', 'email', 'redirect_to'],
)]
final readonly class RegisterHandler implements RequestHandlerInterface
{
    public function __construct(
        private RegistrationWorkflow $registrationWorkflow,
        private SafeRedirectTarget $safeRedirectTarget,
        private TurnstileRequestGuard $turnstileRequestGuard,
        private ValidationRedirectState $validationRedirectState,
        private PendingEmailVerificationSession $pendingEmailVerificationSession,
        private OAuthSessionContext $oAuthSessionContext,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RegisterRequest $form */
        $form       = $request->getAttribute(RegisterRequest::class);
        $redirectTo = $this->safeRedirectTarget->normalize($form->redirectTo);

        $registrationResult = $this->registrationWorkflow->handle($form->email, $form->displayName, $form->password, $redirectTo, $this->turnstileRequestGuard->verify($request));

        if (RegistrationStatus::Disabled === $registrationResult->status) {
            $this->validationRedirectState->errors($request, [
                'registration' => ['Registration is currently closed.'],
            ]);

            return new RedirectResponse('/auth/login');
        }

        if (RegistrationStatus::ChallengeFailed === $registrationResult->status) {
            $this->validationRedirectState->errors($request, $this->turnstileRequestGuard->error());
            $this->validationRedirectState->old($request, [
                'display_name' => $form->displayName,
                'email'        => $form->email,
                'redirect_to'  => $redirectTo,
            ]);

            return new RedirectResponse('/auth/register');
        }

        // Store user UUID in session for email verification
        $session = $request->getAttribute('session');
        if ($session instanceof SessionInterface) {
            $this->oAuthSessionContext->consumePendingEmailContext($session);
            $this->pendingEmailVerificationSession->startRegistration($session, $registrationResult->userId, $redirectTo);
        }

        return new RedirectResponse('/auth/verification');
    }
}
