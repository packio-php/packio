<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Identity\Api\OAuthProvider;
use Packio\Identity\Infrastructure\Dto\OAuthCallbackInput;
use Packio\Identity\Infrastructure\Dto\OAuthState;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderAvailabilityResolver;
use Packio\Identity\Infrastructure\OAuth\SourceOAuthCallbackWorkflow;
use Packio\Identity\Infrastructure\Support\OAuthCallbackStateValidator;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function is_string;

#[Get('/auth/oauth/:provider/source-callback', name: 'auth.oauth.source_callback')]
final readonly class OAuthSourceCallbackHandler implements RequestHandlerInterface
{
    public function __construct(
        private OAuthSessionContext $oAuthSessionContext,
        private OAuthCallbackStateValidator $oAuthCallbackStateValidator,
        private OAuthProviderAvailabilityResolver $oauthProviderAvailabilityResolver,
        private SourceOAuthCallbackWorkflow $sourceOAuthCallbackWorkflow,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private SourceOAuthPopupRenderer $sourceOAuthPopupRenderer,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $provider = $request->getAttribute('provider');

        if (! is_string($provider) || '' === $provider) {
            return new JsonResponse([
                'error' => 'OAuth provider not supported.',
            ], 404);
        }

        $providerEnum = 'gitlab_self_hosted' === $provider
            ? null
            : $this->oauthProviderAvailabilityResolver->resolveSourceConnectProvider($provider);
        if ('gitlab_self_hosted' === $provider && ! $this->oauthProviderAvailabilityResolver->gitLabSelfHostedSourceConnectEnabled()) {
            return new JsonResponse([
                'error' => 'OAuth provider not supported.',
            ], 404);
        }

        if ('gitlab_self_hosted' !== $provider && ! $providerEnum instanceof OAuthProvider) {
            return new JsonResponse([
                'error' => 'OAuth provider not supported.',
            ], 404);
        }
        $providerValue = $providerEnum instanceof OAuthProvider ? $providerEnum->value : $provider;

        $session            = $request->getAttribute('session');
        $oAuthCallbackInput = OAuthCallbackInput::fromRequest($request);

        if (! $this->oAuthCallbackStateValidator->isValid($this->oAuthSessionContext->getState($session), $oAuthCallbackInput)) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'Invalid OAuth state. Please try again.');

            return new RedirectResponse('/organizations');
        }

        $this->oAuthSessionContext->clearState($session);
        $codeVerifier = OAuthProvider::Codeberg === $providerEnum
            ? $this->oAuthSessionContext->consumePkceCodeVerifier($session)
            : null;

        if (
            ! ($oAuthCallbackInput->decodedState instanceof OAuthState)
            || ! $oAuthCallbackInput->decodedState->isConnectSourceAction()
            || (
                null !== $oAuthCallbackInput->decodedState->provider
                && $oAuthCallbackInput->decodedState->provider !== $providerValue
            )
        ) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'Invalid OAuth state for source connection.');

            return new RedirectResponse('/organizations');
        }

        $currentUser = RequestContextAttributes::currentUser($request);
        if (! $currentUser instanceof UserDto) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'You must be logged in to connect a source.');

            return new RedirectResponse('/auth/login');
        }

        $sourceOAuthContext = $this->oAuthSessionContext->consumeSourceContext($session);

        if ('' === $oAuthCallbackInput->code) {
            if ($sourceOAuthContext->popup) {
                return $this->sourceOAuthPopupRenderer->render(false, $providerValue, null, 'OAuth authorization failed. Please try again.');
            }
            $this->inertiaNotificationMessenger->toast($request, 'error', 'OAuth authorization failed. Please try again.');

            return new RedirectResponse('/organizations');
        }

        try {
            $sourceOAuthAccount = $this->sourceOAuthCallbackWorkflow->handle($providerValue, $oAuthCallbackInput->code, $currentUser->id, $sourceOAuthContext->organizationSlug, $sourceOAuthContext->providerInstanceId, $codeVerifier);
        } catch (RuntimeException) {
            if ($sourceOAuthContext->popup) {
                return $this->sourceOAuthPopupRenderer->render(false, $providerValue, null, 'OAuth authorization failed. Please try again.');
            }

            $this->inertiaNotificationMessenger->toast($request, 'error', 'OAuth authorization failed. Please try again.');

            return new RedirectResponse('/organizations');
        }

        if ($sourceOAuthContext->popup) {
            return $this->sourceOAuthPopupRenderer->render(
                true,
                $sourceOAuthAccount->provider,
                $sourceOAuthAccount->id,
            );
        }

        $redirectPath = '' !== $sourceOAuthContext->organizationSlug
            ? '/organization/' . $sourceOAuthContext->organizationSlug . '/sources?oauth_just_connected=1'
            : '/organizations';

        $this->inertiaNotificationMessenger->toast($request, 'success', 'Source authorization connected successfully!');

        return new RedirectResponse($redirectPath);
    }
}
