<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Service\SafeRedirectTarget;
use Packio\Identity\Api\OAuthApiFacadeInterface;
use Packio\Identity\Api\OAuthProvider;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderAvailabilityResolver;
use Packio\Identity\Infrastructure\Support\OAuthPkce;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\GuestOnly;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function bin2hex;
use function is_string;
use function random_bytes;

#[Get('/auth/oauth/:provider/redirect', name: 'auth.oauth.redirect')]
#[GuestOnly]
final readonly class OAuthRedirectHandler implements RequestHandlerInterface
{
    public function __construct(
        private OAuthApiFacadeInterface $oAuthApiFacade,
        private OAuthSessionContext $oAuthSessionContext,
        private OAuthProviderAvailabilityResolver $oauthProviderAvailabilityResolver,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private SafeRedirectTarget $safeRedirectTarget,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $provider = $request->getAttribute('provider');

        if (! is_string($provider) || '' === $provider) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'OAuth provider not supported.');

            return new RedirectResponse('/auth/login');
        }

        $providerEnum = $this->oauthProviderAvailabilityResolver->resolveAuthProvider($provider);
        if (! $providerEnum instanceof OAuthProvider) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'OAuth provider not supported.');

            return new RedirectResponse('/auth/login');
        }

        $state   = bin2hex(random_bytes(16));
        $session = $request->getAttribute('session');
        $this->oAuthSessionContext->storeState($session, $state);
        $queryParams = $request->getQueryParams();
        $this->oAuthSessionContext->storeRedirectTarget(
            $session,
            $this->safeRedirectTarget->normalize($queryParams['redirect_to'] ?? ''),
        );

        $codeChallenge = null;
        if (OAuthProvider::Codeberg === $providerEnum) {
            $codeVerifier = OAuthPkce::createCodeVerifier();
            $this->oAuthSessionContext->storePkceCodeVerifier($session, $codeVerifier);
            $codeChallenge = OAuthPkce::createCodeChallenge($codeVerifier);
        }

        return new RedirectResponse(
            $this->oAuthApiFacade->getAuthorizationUrl($providerEnum->value, $state, $codeChallenge),
            302,
        );
    }
}
