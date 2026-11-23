<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Service\SafeRedirectTarget;
use Packio\Identity\Api\OAuthProvider;
use Packio\Identity\Application\Command\OAuth\BrowserOAuthWorkflowInterface;
use Packio\Identity\Infrastructure\Dto\OAuthCallbackInput;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderAvailabilityResolver;
use Packio\Identity\Infrastructure\Support\OAuthCallbackStateValidator;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function is_string;

#[Get('/auth/oauth/:provider/callback', name: 'auth.oauth.callback')]
final readonly class OAuthCallbackHandler implements RequestHandlerInterface
{
    public function __construct(
        private BrowserOAuthWorkflowInterface $browserOAuthWorkflow,
        private OAuthOutcomePresenter $oAuthOutcomePresenter,
        private OAuthSessionContext $oAuthSessionContext,
        private OAuthCallbackStateValidator $oAuthCallbackStateValidator,
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

        $oAuthCallbackInput = OAuthCallbackInput::fromRequest($request);

        if ($oAuthCallbackInput->decodedState?->isConnectSourceAction()) {
            $query  = $request->getUri()->getQuery();
            $target = '/auth/oauth/' . $providerEnum->value . '/source-callback';

            if ('' !== $query) {
                $target .= '?' . $query;
            }

            return new RedirectResponse($target);
        }

        $session = $request->getAttribute('session');
        if (! $this->oAuthCallbackStateValidator->isValid($this->oAuthSessionContext->getState($session), $oAuthCallbackInput)) {
            $this->oAuthSessionContext->consumeRedirectTarget($session);
            $this->inertiaNotificationMessenger->toast($request, 'error', 'Invalid OAuth state. Please try again.');

            return new RedirectResponse('/auth/login');
        }

        $redirectTo = $this->safeRedirectTarget->normalize(
            $this->oAuthSessionContext->consumeRedirectTarget($session),
        );

        $this->oAuthSessionContext->clearState($session);
        $codeVerifier = OAuthProvider::Codeberg === $providerEnum
            ? $this->oAuthSessionContext->consumePkceCodeVerifier($session)
            : null;

        if ('' === $oAuthCallbackInput->code) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'OAuth authorization failed. Please try again.');

            return new RedirectResponse('/auth/login');
        }

        try {
            $result = $this->browserOAuthWorkflow->handle($providerEnum->value, $oAuthCallbackInput->code, $redirectTo, $codeVerifier);
        } catch (RuntimeException) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'OAuth authentication failed. Please try again.');

            return new RedirectResponse('/auth/login');
        }

        return $this->oAuthOutcomePresenter->present($request, $result, $redirectTo);
    }
}
