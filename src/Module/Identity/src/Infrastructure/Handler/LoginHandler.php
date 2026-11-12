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
use Packio\Identity\Application\Command\SignInWorkflow;
use Packio\Identity\Application\Query\IdentityQueryService;
use Packio\Identity\Application\Result\SignInStatus;
use Packio\Identity\Infrastructure\Request\LoginRequest;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use Sirix\Mezzio\Authentication\Attribute\GuestOnly;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/auth/login', name: 'auth.session.login')]
#[GuestOnly]
#[RateLimit(
    key: 'login',
    by: ['ip'],
    response: 'redirect',
    message: 'Too many login attempts. Please try again later.',
    redirectTo: '/auth/login',
    notify: 'flash'
)]
#[MapRequest(body: LoginRequest::class)]
#[ValidateMappedRequest(LoginRequest::class)]
#[ValidationFailed(
    redirectTo: '/auth/login',
    message: 'Invalid request data.',
    notify: ValidationNotify::None,
    oldInput: ['email', 'redirect_to'],
)]
final readonly class LoginHandler implements RequestHandlerInterface
{
    public function __construct(
        private SignInWorkflow $signInWorkflow,
        private AuthManagerInterface $authManager,
        private SafeRedirectTarget $safeRedirectTarget,
        private TurnstileRequestGuard $turnstileRequestGuard,
        private IdentityQueryService $identityQueryService,
        private PendingEmailVerificationSession $pendingEmailVerificationSession,
        private ValidationRedirectState $validationRedirectState,
        private OAuthSessionContext $oAuthSessionContext,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var LoginRequest $form */
        $form       = $request->getAttribute(LoginRequest::class);
        $redirectTo = $this->safeRedirectTarget->normalize($form->redirectTo);

        if (! $this->turnstileRequestGuard->verify($request)) {
            $this->validationRedirectState->errors($request, $this->turnstileRequestGuard->error());
            $this->validationRedirectState->old($request, [
                'email'       => $form->email,
                'redirect_to' => $redirectTo,
            ]);

            return new RedirectResponse('/auth/login');
        }

        $signInResult = $this->signInWorkflow->handle($form->email, $form->password);
        if (SignInStatus::InvalidCredentials === $signInResult->status || null === $signInResult->userId) {
            $this->validationRedirectState->errors($request, [
                'email'    => ['Invalid credentials.'],
                'password' => [' '],
            ]);
            $this->validationRedirectState->old($request, [
                'email'       => $form->email,
                'redirect_to' => $redirectTo,
            ]);

            return new RedirectResponse('/auth/login');
        }

        $session = $request->getAttribute('session');
        $this->oAuthSessionContext->consumePendingEmailContext($session);
        if ($session instanceof SessionInterface) {
            $this->pendingEmailVerificationSession->clear($session);
        }

        if (SignInStatus::Banned === $signInResult->status) {
            return $this->authManager->login(
                $request,
                new RedirectResponse('/account/banned'),
                [
                    'user_id'                 => $signInResult->userId,
                    'credentials_fingerprint' => $this->identityQueryService->credentialsFingerprint($signInResult->userId),
                ],
            );
        }

        // Email not verified - redirect to verification
        if (SignInStatus::VerificationRequired === $signInResult->status) {
            $session = $request->getAttribute('session');
            if ($session instanceof SessionInterface) {
                $this->pendingEmailVerificationSession->start($session, $signInResult->userId, $redirectTo);
            }

            return new RedirectResponse('/auth/verification');
        }

        return $this->authManager->login(
            $request,
            new RedirectResponse('' !== $redirectTo ? $redirectTo : '/organizations'),
            [
                'user_id'                 => $signInResult->userId,
                'credentials_fingerprint' => $this->identityQueryService->credentialsFingerprint($signInResult->userId),
            ],
        );
    }
}
