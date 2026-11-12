<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Security\Honeypot\HoneypotFieldNameGenerator;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Infrastructure\Request\ResetPasswordLinkRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\GuestOnly;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[GuestOnly]
#[Get('/auth/reset-password/:selector/:token', name: 'auth.reset-password')]
#[MapRequest(route: ResetPasswordLinkRequest::class)]
#[ValidateMappedRequest(ResetPasswordLinkRequest::class)]
final readonly class ResetPasswordPageHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private TurnstileRequestGuard $turnstileRequestGuard,
        private HoneypotFieldNameGenerator $honeypotFieldNameGenerator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        /** @var ResetPasswordLinkRequest $form */
        $form = $request->getAttribute(ResetPasswordLinkRequest::class);

        $passwordResetResultDto = $this->identityApiFacade->validatePasswordResetToken($form->selector, $form->token);

        if (! $passwordResetResultDto->success) {
            $this->inertiaNotificationMessenger->flash(
                $request,
                'error',
                'This password reset link is invalid or has expired.'
            );

            return new RedirectResponse('/auth/forgot-password');
        }

        return $inertia->render('Auth/ResetPassword', [
            'selector'  => $form->selector,
            'token'     => $form->token,
            'turnstile' => $this->turnstileRequestGuard->payload(),
            'honeypot'  => [
                'field_name' => $this->honeypotFieldNameGenerator->generate(),
            ],
        ]);
    }
}
