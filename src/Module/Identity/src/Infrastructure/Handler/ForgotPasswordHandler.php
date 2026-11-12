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
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Infrastructure\Request\ForgotPasswordRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use Sirix\Mezzio\Authentication\Attribute\GuestOnly;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Throwable;

#[Post('/auth/forgot-password', name: 'auth.forgot-password.submit')]
#[GuestOnly]
#[RateLimit(
    key: 'password_reset_request',
    by: ['ip'],
    response: 'redirect',
    message: 'Too many requests. Please try again later.',
    redirectTo: '/auth/forgot-password',
    notify: 'flash'
)]
#[MapRequest(body: ForgotPasswordRequest::class)]
#[ValidateMappedRequest(ForgotPasswordRequest::class)]
#[ValidationFailed(
    redirectTo: '/auth/forgot-password',
    message: 'Invalid request data.',
    notify: ValidationNotify::None,
    oldInput: ['email'],
)]
final readonly class ForgotPasswordHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private ClientIpResolverInterface $clientIpResolver,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private ValidationRedirectState $validationRedirectState,
        private TurnstileRequestGuard $turnstileRequestGuard,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $ip = $this->clientIpResolver->resolve($request);

        /** @var ForgotPasswordRequest $form */
        $form = $request->getAttribute(ForgotPasswordRequest::class);

        if (! $this->turnstileRequestGuard->verify($request)) {
            $this->validationRedirectState->errors($request, $this->turnstileRequestGuard->error());
            $this->validationRedirectState->old($request, [
                'email' => $form->email,
            ]);

            return new RedirectResponse('/auth/forgot-password');
        }

        try {
            $this->identityApiFacade->requestPasswordReset(
                $form->email,
                $ip,
                (string) ($request->getServerParams()['HTTP_USER_AGENT'] ?? ''),
            );
        } catch (Throwable) {
        }

        $this->inertiaNotificationMessenger->flash($request, 'success', 'If an account exists for that email, we have sent a password reset link.');

        return new RedirectResponse('/auth/forgot-password');
    }
}
