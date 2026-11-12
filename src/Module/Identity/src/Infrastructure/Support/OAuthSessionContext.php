<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Support;

use Common\Security\SecretEncryptorInterface;
use Mezzio\Session\SessionInterface;
use Packio\Identity\Infrastructure\Dto\PendingOAuthEmailContext;
use Packio\Identity\Infrastructure\Dto\SourceOAuthContext;

use function is_array;
use function is_string;
use function time;

final readonly class OAuthSessionContext
{
    private const string OAUTH_STATE_KEY                    = 'oauth_state';
    private const string OAUTH_CONNECT_ORGANIZATION_KEY     = 'oauth_connect_organization';
    private const string OAUTH_SOURCE_PROVIDER_INSTANCE_KEY = 'oauth_source_provider_instance_id';
    private const string OAUTH_SOURCE_POPUP_KEY             = 'oauth_source_popup';
    private const string OAUTH_REDIRECT_TARGET_KEY          = 'oauth_redirect_target';
    private const string OAUTH_PKCE_CODE_VERIFIER_KEY       = 'oauth_pkce_code_verifier';
    private const string OAUTH_PENDING_EMAIL_CONTEXT_KEY    = 'oauth_pending_email_context';
    private const int OAUTH_PENDING_EMAIL_TTL_SECONDS       = 900;

    public function __construct(private SecretEncryptorInterface $secretEncryptor) {}

    public function storeState(?SessionInterface $session, string $state): void
    {
        if (! $session instanceof SessionInterface) {
            return;
        }

        $session->set(self::OAUTH_STATE_KEY, $state);
    }

    public function getState(?SessionInterface $session): string
    {
        if (! $session instanceof SessionInterface) {
            return '';
        }

        return (string) $session->get(self::OAUTH_STATE_KEY, '');
    }

    public function clearState(?SessionInterface $session): void
    {
        if (! $session instanceof SessionInterface) {
            return;
        }

        $session->unset(self::OAUTH_STATE_KEY);
    }

    public function storeRedirectTarget(?SessionInterface $session, string $redirectTarget): void
    {
        if (! $session instanceof SessionInterface) {
            return;
        }

        if ('' === $redirectTarget) {
            $session->unset(self::OAUTH_REDIRECT_TARGET_KEY);

            return;
        }

        $session->set(self::OAUTH_REDIRECT_TARGET_KEY, $redirectTarget);
    }

    public function consumeRedirectTarget(?SessionInterface $session): string
    {
        if (! $session instanceof SessionInterface) {
            return '';
        }

        $redirectTarget = (string) $session->get(self::OAUTH_REDIRECT_TARGET_KEY, '');
        $session->unset(self::OAUTH_REDIRECT_TARGET_KEY);

        return $redirectTarget;
    }

    public function storePkceCodeVerifier(?SessionInterface $session, string $codeVerifier): void
    {
        if (! $session instanceof SessionInterface) {
            return;
        }

        $session->set(self::OAUTH_PKCE_CODE_VERIFIER_KEY, $codeVerifier);
    }

    public function consumePkceCodeVerifier(?SessionInterface $session): ?string
    {
        if (! $session instanceof SessionInterface) {
            return null;
        }

        $codeVerifier = $session->get(self::OAUTH_PKCE_CODE_VERIFIER_KEY);
        $session->unset(self::OAUTH_PKCE_CODE_VERIFIER_KEY);

        return is_string($codeVerifier) && '' !== $codeVerifier ? $codeVerifier : null;
    }

    public function storePendingEmailContext(?SessionInterface $session, PendingOAuthEmailContext $pendingOAuthEmailContext): void
    {
        if (! $session instanceof SessionInterface) {
            return;
        }

        $payload                  = $pendingOAuthEmailContext->toArray();
        $payload['access_token']  = $this->encryptSecret($pendingOAuthEmailContext->accessToken);
        $payload['refresh_token'] = null !== $pendingOAuthEmailContext->refreshToken
            ? $this->encryptSecret($pendingOAuthEmailContext->refreshToken)
            : null;

        $session->set(self::OAUTH_PENDING_EMAIL_CONTEXT_KEY, $payload);
    }

    public function pendingEmailContext(?SessionInterface $session): ?PendingOAuthEmailContext
    {
        if (! $session instanceof SessionInterface) {
            return null;
        }

        $value = $session->get(self::OAUTH_PENDING_EMAIL_CONTEXT_KEY);
        if (! is_array($value)) {
            return null;
        }

        $context = PendingOAuthEmailContext::fromArray($value);
        if (! $context instanceof PendingOAuthEmailContext) {
            return null;
        }

        if ($context->createdAt + self::OAUTH_PENDING_EMAIL_TTL_SECONDS < time()) {
            $session->unset(self::OAUTH_PENDING_EMAIL_CONTEXT_KEY);

            return null;
        }

        return new PendingOAuthEmailContext(
            provider: $context->provider,
            accessToken: $this->decryptSecret($context->accessToken),
            refreshToken: null !== $context->refreshToken ? $this->decryptSecret($context->refreshToken) : null,
            expiresIn: $context->expiresIn,
            providerUserId: $context->providerUserId,
            providerUsername: $context->providerUsername,
            providerDisplayName: $context->providerDisplayName,
            providerAvatarUrl: $context->providerAvatarUrl,
            registrationRedirectTarget: $context->registrationRedirectTarget,
            createdAt: $context->createdAt,
        );
    }

    public function consumePendingEmailContext(?SessionInterface $session): ?PendingOAuthEmailContext
    {
        $context = $this->pendingEmailContext($session);

        if ($session instanceof SessionInterface) {
            $session->unset(self::OAUTH_PENDING_EMAIL_CONTEXT_KEY);
            $session->unset('oauth_continuation_required');
        }

        return $context;
    }

    public function requireContinuation(?SessionInterface $session): void
    {
        $session?->set('oauth_continuation_required', true);
    }

    public function continuationRequired(?SessionInterface $session): bool
    {
        return true === $session?->get('oauth_continuation_required', false);
    }

    public function storeSourceContext(
        ?SessionInterface $session,
        string $organizationSlug,
        bool $popup,
        string $providerInstanceId = ''
    ): void {
        if (! $session instanceof SessionInterface) {
            return;
        }

        $session->set(self::OAUTH_CONNECT_ORGANIZATION_KEY, $organizationSlug);
        $session->set(self::OAUTH_SOURCE_POPUP_KEY, $popup);
        $session->set(self::OAUTH_SOURCE_PROVIDER_INSTANCE_KEY, $providerInstanceId);
    }

    public function consumeSourceContext(?SessionInterface $session): SourceOAuthContext
    {
        if (! $session instanceof SessionInterface) {
            return new SourceOAuthContext('', false);
        }

        $organizationSlug   = (string) $session->get(self::OAUTH_CONNECT_ORGANIZATION_KEY, '');
        $popup              = true === $session->get(self::OAUTH_SOURCE_POPUP_KEY, false);
        $providerInstanceId = (string) $session->get(self::OAUTH_SOURCE_PROVIDER_INSTANCE_KEY, '');

        $session->unset(self::OAUTH_CONNECT_ORGANIZATION_KEY);
        $session->unset(self::OAUTH_SOURCE_POPUP_KEY);
        $session->unset(self::OAUTH_SOURCE_PROVIDER_INSTANCE_KEY);

        return new SourceOAuthContext($organizationSlug, $popup, $providerInstanceId);
    }

    private function encryptSecret(string $secret): string
    {
        return $this->secretEncryptor->encrypt($secret);
    }

    private function decryptSecret(string $secret): string
    {
        return $this->secretEncryptor->decrypt($secret) ?? '';
    }
}
