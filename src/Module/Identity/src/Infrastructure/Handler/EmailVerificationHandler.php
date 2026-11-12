<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Session\SessionInterface;
use Packio\Application\Security\Honeypot\HoneypotFieldNameGenerator;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/auth/verification', name: 'auth.verification')]
final readonly class EmailVerificationHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private HoneypotFieldNameGenerator $honeypotFieldNameGenerator,
        private BrandingConfig $brandingConfig,
        private TurnstileRequestGuard $turnstileRequestGuard,
        private PendingEmailVerificationSession $pendingEmailVerificationSession = new PendingEmailVerificationSession(),
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        $userUuid = $this->getUserUuidFromSession($request);
        if (null === $userUuid) {
            return new RedirectResponse('/auth/login');
        }

        return $inertia->render('Auth/EmailVerification', [
            'turnstile'           => $this->turnstileRequestGuard->payload(),
            'honeypot'            => [
                'field_name' => $this->honeypotFieldNameGenerator->generate(),
            ],
            'resend_available_in' => $this->pendingEmailVerificationSession->registrationDelay($request->getAttribute('session')) ?? $this->identityApiFacade->emailVerificationRemainingResendDelay($userUuid),
            'og_title'            => 'Verify email — ' . $this->brandingConfig->productName(),
            'og_description'      => 'Verify your email address for ' . $this->brandingConfig->productName(),
        ]);
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
