<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Identity\Application\Command\OAuth\ResolveOAuthCallbackCommand;
use Packio\Identity\Application\Query\IdentityQueryService;
use Packio\Identity\Application\Result\OAuthCallbackResult;
use Packio\Identity\Application\Result\OAuthCallbackStatus;
use Packio\Identity\Infrastructure\Dto\PendingOAuthEmailContext;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;

final readonly class OAuthOutcomePresenter
{
    public function __construct(
        private AuthManagerInterface $authManager,
        private OAuthSessionContext $oAuthSessionContext,
        private PendingEmailVerificationSession $pendingEmailVerificationSession,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private IdentityQueryService $identityQueryService,
    ) {}

    public function present(
        ServerRequestInterface $serverRequest,
        OAuthCallbackResult $oAuthCallbackResult,
        string $redirectTo
    ): ResponseInterface {
        $session = $serverRequest->getAttribute('session');
        if (OAuthCallbackStatus::EmailRequired === $oAuthCallbackResult->status && $oAuthCallbackResult->pendingOAuth instanceof ResolveOAuthCallbackCommand) {
            $this->oAuthSessionContext->consumePendingEmailContext($session);
            if (null !== $session) {
                $this->pendingEmailVerificationSession->clear($session);
            }
            $pending = $oAuthCallbackResult->pendingOAuth;
            $this->oAuthSessionContext->storePendingEmailContext($session, PendingOAuthEmailContext::fromOAuth($pending->provider, $pending->tokens, $pending->userData, $pending->registrationRedirectTarget));

            return new RedirectResponse('/auth/oauth/email');
        }

        if (OAuthCallbackStatus::VerificationRequired === $oAuthCallbackResult->status && null !== $oAuthCallbackResult->userId) {
            if (null !== $session) {
                $this->pendingEmailVerificationSession->start($session, $oAuthCallbackResult->userId, $redirectTo);
            }

            return new RedirectResponse('/auth/verification');
        }

        if (null !== $oAuthCallbackResult->userId && (OAuthCallbackStatus::Authenticated === $oAuthCallbackResult->status || OAuthCallbackStatus::Banned === $oAuthCallbackResult->status)) {
            $this->oAuthSessionContext->consumePendingEmailContext($session);
            if (null !== $session) {
                $this->pendingEmailVerificationSession->clear($session);
            }

            return $this->authManager->login($serverRequest, new RedirectResponse(OAuthCallbackStatus::Banned === $oAuthCallbackResult->status ? '/account/banned' : ($redirectTo ?: '/organizations')), [
                'user_id'                 => $oAuthCallbackResult->userId,
                'credentials_fingerprint' => $this->identityQueryService->credentialsFingerprint($oAuthCallbackResult->userId),
            ]);
        }

        if (OAuthCallbackStatus::SessionExpired === $oAuthCallbackResult->status) {
            $this->oAuthSessionContext->consumePendingEmailContext($session);
            if (null !== $session) {
                $this->pendingEmailVerificationSession->clear($session);
            }
            $this->inertiaNotificationMessenger->toast($serverRequest, 'error', 'OAuth session expired. Please try again.');

            return new RedirectResponse('/auth/login');
        }

        $this->inertiaNotificationMessenger->toast($serverRequest, 'error', 'OAuth authentication failed. Please try again.');

        return new RedirectResponse('/auth/login');
    }
}
