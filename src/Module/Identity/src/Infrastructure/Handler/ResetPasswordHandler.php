<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Application\Security\Honeypot\HoneypotFieldNameGenerator;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Identity\Application\Command\PasswordResetWorkflow;
use Packio\Identity\Infrastructure\Request\ResetPasswordRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\GuestOnly;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

use function rawurlencode;

#[Post('/auth/reset-password', name: 'auth.reset-password.submit')]
#[GuestOnly]
#[RateLimit(
    key: 'password_reset_submit',
    by: ['ip'],
    response: 'redirect',
    message: 'Too many requests. Please try again later.',
    redirectTo: '/auth/forgot-password',
)]
#[MapRequest(body: ResetPasswordRequest::class)]
#[ValidateMappedRequest(ResetPasswordRequest::class)]
#[ValidationFailed(
    notify: ValidationNotify::Flash,
)]
final readonly class ResetPasswordHandler implements RequestHandlerInterface
{
    public function __construct(
        private PasswordResetWorkflow $passwordResetWorkflow,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private ValidationRedirectState $validationRedirectState,
        private TurnstileRequestGuard $turnstileRequestGuard,
        private HoneypotFieldNameGenerator $honeypotFieldNameGenerator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        /** @var ResetPasswordRequest $form */
        $form = $request->getAttribute(ResetPasswordRequest::class);

        if (! $this->turnstileRequestGuard->verify($request)) {
            $this->validationRedirectState->errors($request, $this->turnstileRequestGuard->error());

            return new RedirectResponse($this->resetPasswordUrl($form->selector, $form->token));
        }

        $passwordResetResult = $this->passwordResetWorkflow->handle($form->selector, $form->token, $form->password);

        if (! $passwordResetResult->success) {
            if ($passwordResetResult->isExpiredToken()) {
                $this->inertiaNotificationMessenger->flash($request, 'error', 'This password reset link has expired. Please request a new one.');
            } elseif ($passwordResetResult->isUsedToken()) {
                $this->inertiaNotificationMessenger->flash($request, 'error', 'This password reset link has already been used. Please request a new one.');
            } elseif ($passwordResetResult->isInvalidToken()) {
                $this->inertiaNotificationMessenger->flash($request, 'error', 'This password reset link is invalid. Please request a new one.');
            } elseif ($passwordResetResult->isValidationFailed()) {
                $inertia->share('errors', [
                    'password' => $passwordResetResult->errors,
                ]);

                return $inertia->render('Auth/ResetPassword', [
                    'selector'  => $form->selector,
                    'token'     => $form->token,
                    'turnstile' => $this->turnstileRequestGuard->payload(),
                    'honeypot'  => [
                        'field_name' => $this->honeypotFieldNameGenerator->generate(),
                    ],
                ]);
            } else {
                $inertia->share('errors', [
                    'email' => ['Unable to reset password. Please try again.'],
                ]);

                return $inertia->render('Auth/ResetPassword', [
                    'selector'  => $form->selector,
                    'token'     => $form->token,
                    'turnstile' => $this->turnstileRequestGuard->payload(),
                    'honeypot'  => [
                        'field_name' => $this->honeypotFieldNameGenerator->generate(),
                    ],
                ]);
            }

            return new RedirectResponse('/auth/forgot-password');
        }

        $this->inertiaNotificationMessenger->flash($request, 'success', 'Password updated. You can now sign in.');

        return new RedirectResponse('/auth/login');
    }

    private function resetPasswordUrl(string $selector, string $token): string
    {
        return '/auth/reset-password/' . rawurlencode($selector) . '/' . rawurlencode($token);
    }
}
