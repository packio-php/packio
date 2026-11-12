<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Security\Honeypot\HoneypotFieldNameGenerator;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Identity\Api\OAuthProvider;
use Packio\Identity\Infrastructure\Dto\PendingOAuthEmailContext;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\GuestOnly;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/auth/oauth/email', name: 'auth.oauth.email')]
#[GuestOnly]
final readonly class OAuthEmailHandler implements RequestHandlerInterface
{
    public function __construct(
        private OAuthSessionContext $oAuthSessionContext,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private HoneypotFieldNameGenerator $honeypotFieldNameGenerator,
        private BrandingConfig $brandingConfig,
        private TurnstileRequestGuard $turnstileRequestGuard,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->oAuthSessionContext->pendingEmailContext($request->getAttribute('session'));
        if (! $context instanceof PendingOAuthEmailContext) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'OAuth session expired. Please try again.');

            return new RedirectResponse('/auth/login');
        }

        $provider = OAuthProvider::tryFrom($context->provider);
        $inertia  = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        return $inertia->render('Auth/OAuthEmail', [
            'provider'          => $context->provider,
            'provider_label'    => $provider?->label() ?? $context->provider,
            'provider_username' => $context->providerUsername,
            'turnstile'         => $this->turnstileRequestGuard->payload(),
            'honeypot'          => [
                'field_name' => $this->honeypotFieldNameGenerator->generate(),
            ],
            'branding'          => $this->brandingConfig->toArray(),
            'og_title'          => 'Complete sign in — ' . $this->brandingConfig->productName(),
            'og_description'    => 'Add an email address to complete OAuth sign in.',
        ]);
    }
}
