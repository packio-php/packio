<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Packio\Application\Security\Honeypot\HoneypotFieldNameGenerator;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Application\Service\SafeRedirectTarget;
use Packio\Identity\Application\Port\RegistrationPolicyPort;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderAvailabilityResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\GuestOnly;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function array_map;

#[Get('/auth/login', name: 'auth.login')]
#[GuestOnly]
final readonly class LoginPageHandler implements RequestHandlerInterface
{
    public function __construct(
        private OAuthProviderAvailabilityResolver $oAuthProviderAvailabilityResolver,
        private SafeRedirectTarget $safeRedirectTarget,
        private TurnstileRequestGuard $turnstileRequestGuard,
        private HoneypotFieldNameGenerator $honeypotFieldNameGenerator,
        private RegistrationPolicyPort $registrationPolicyPort,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia     = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        $queryParams = $request->getQueryParams();
        $redirectTo  = $this->safeRedirectTarget->normalize($queryParams['redirect_to'] ?? '');

        return $inertia->render('Auth/Login', [
            'redirect_to'       => $redirectTo,
            'turnstile'         => $this->turnstileRequestGuard->payload(),
            'honeypot'          => [
                'field_name' => $this->honeypotFieldNameGenerator->generate(),
            ],
            'registration_open' => $this->registrationPolicyPort->allowsRegistration($redirectTo),
            'oauth_providers'   => array_map(
                static fn ($provider): array => $provider->toAuthPagePayload(),
                $this->oAuthProviderAvailabilityResolver->forAuthPages(),
            ),
        ]);
    }
}
