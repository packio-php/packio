<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command\OAuth;

use Packio\Identity\Application\Result\OAuthCallbackResult;
use Packio\Identity\Domain\ValueObject\OAuthProvider;

final readonly class BrowserOAuthWorkflow implements BrowserOAuthWorkflowInterface
{
    public function __construct(private OAuthProviderInterface $oAuthProvider, private OAuthCallbackUseCase $oAuthCallbackUseCase) {}

    public function handle(string $provider, string $code, string $redirectTo = '', ?string $codeVerifier = null): OAuthCallbackResult
    {
        $provider             = OAuthProvider::from($provider);
        $oAuthTokenResponse   = $this->oAuthProvider->exchangeCodeForToken($provider, $code, $codeVerifier);
        $oAuthUserData        = $this->oAuthProvider->fetchUser($provider, $oAuthTokenResponse->accessToken);

        return $this->oAuthCallbackUseCase->handle(new ResolveOAuthCallbackCommand($provider, $oAuthTokenResponse, $oAuthUserData, registrationRedirectTarget: $redirectTo));
    }
}
