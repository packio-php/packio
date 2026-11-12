<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Identity\Api\OAuthApiFacadeInterface;
use Packio\Identity\Api\OAuthProvider;
use Packio\Identity\Infrastructure\Dto\OAuthState;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderAvailabilityResolver;
use Packio\Identity\Infrastructure\Support\OAuthPkce;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function bin2hex;
use function is_string;
use function random_bytes;

#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get(
    '/organization/:organization/auth/oauth/:provider/source-redirect',
    name: 'auth.oauth.source_redirect',
    middleware: [RequireExistingOrganizationMiddleware::class],
)]
final readonly class OAuthSourceRedirectHandler implements RequestHandlerInterface
{
    public function __construct(
        private OAuthApiFacadeInterface $oAuthApiFacade,
        private OAuthSessionContext $oAuthSessionContext,
        private OAuthProviderAvailabilityResolver $oauthProviderAvailabilityResolver,
        private SourceApiFacadeInterface $sourceApiFacade,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $provider = $request->getAttribute('provider');

        if (! is_string($provider) || '' === $provider) {
            return new JsonResponse([
                'error' => 'OAuth provider not supported.',
            ], 404);
        }

        $params             = $request->getQueryParams();
        $routeOrganization  = $request->getAttribute('organization');
        $organizationSlug   = is_string($routeOrganization) ? $routeOrganization : '';
        $popup              = '1' === (string) ($params['popup'] ?? '');
        $providerInstanceId = is_string($params['provider_instance_id'] ?? null) ? $params['provider_instance_id'] : '';
        $providerEnum       = null;
        $providerValue      = $provider;

        if ('gitlab_self_hosted' !== $provider) {
            $providerEnum = $this->oauthProviderAvailabilityResolver->resolveSourceConnectProvider($provider);
            if (! $providerEnum instanceof OAuthProvider) {
                return new JsonResponse([
                    'error' => 'OAuth provider not supported.',
                ], 404);
            }
            $providerValue = $providerEnum->value;
        } elseif ('' === $organizationSlug || '' === $providerInstanceId) {
            return new JsonResponse([
                'error' => 'Provider instance is required.',
            ], 422);
        } elseif (! $this->gitLabSelfHostedInstanceAvailable($request, $providerInstanceId)) {
            return new JsonResponse([
                'error' => 'OAuth provider not supported.',
            ], 404);
        }

        $rawState     = bin2hex(random_bytes(16));
        $encodedState = OAuthState::forConnectSource(
            $rawState,
            '' !== $organizationSlug ? $organizationSlug : null,
            $providerValue,
        )->encode();

        $session = $request->getAttribute('session');
        $this->oAuthSessionContext->storeState($session, $rawState);
        $this->oAuthSessionContext->storeSourceContext($session, $organizationSlug, $popup, $providerInstanceId);

        $codeChallenge = null;
        if (OAuthProvider::Codeberg === $providerEnum) {
            $codeVerifier = OAuthPkce::createCodeVerifier();
            $this->oAuthSessionContext->storePkceCodeVerifier($session, $codeVerifier);
            $codeChallenge = OAuthPkce::createCodeChallenge($codeVerifier);
        }

        if ('gitlab_self_hosted' === $provider) {
            return new RedirectResponse(
                $this->sourceApiFacade->sourceProviderInstanceAuthorizationUrl(
                    $organizationSlug,
                    $provider,
                    $providerInstanceId,
                    $encodedState,
                    $codeChallenge,
                ),
                302,
            );
        }

        return new RedirectResponse(
            $this->oAuthApiFacade->getSourceAuthorizationUrl($providerValue, $encodedState, $codeChallenge),
            302,
        );
    }

    private function gitLabSelfHostedInstanceAvailable(ServerRequestInterface $serverRequest, string $providerInstanceId): bool
    {
        $organization = RequestContextAttributes::currentOrganization($serverRequest);
        if (! $organization instanceof OrganizationDto) {
            return false;
        }

        foreach ($this->sourceApiFacade->sourceProviderInstances($organization->id, 'gitlab_self_hosted') as $sourceProviderInstanceDto) {
            if ($sourceProviderInstanceDto->id !== $providerInstanceId) {
                continue;
            }

            return $this->oauthProviderAvailabilityResolver->gitLabSelfHostedSourceInstanceAvailable(
                instanceExists: true,
                instanceActive: 'active' === $sourceProviderInstanceDto->status,
                supportsOAuth: 'oauth2' === $sourceProviderInstanceDto->authMode || 'oauth2_and_token' === $sourceProviderInstanceDto->authMode,
                credentialsConfigured: '' !== $sourceProviderInstanceDto->clientId && $sourceProviderInstanceDto->hasClientSecret,
            );
        }

        return false;
    }
}
