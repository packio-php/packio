<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Source\Application\Security\ProviderAuthorizationSecrets;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Psr\Log\LoggerInterface;

use function is_string;

/**
 * Resolves provider access tokens for source operations.
 *
 * Operational source resolution uses the linked SourceOAuthAccount as the
 * canonical source of truth for OAuth-backed authorizations.
 *
 * Read-only authorization resolution also treats SourceOAuthAccount as the
 * canonical source of truth for OAuth-backed authorizations. It never falls
 * back to copied ProviderAuthorization.configuration tokens.
 *
 * For non-OAuth authorizations, only the copied token is used.
 */
final readonly class SourceSecretResolver implements SecretResolverInterface
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository,
        private ProviderAuthorizationSecrets $providerAuthorizationSecrets,
        private SourceOAuthTokenResolver $sourceOAuthTokenResolver,
        private SourceWebhookSecrets $sourceWebhookSecrets,
        private ?LoggerInterface $logger = null,
    ) {}

    public function resolveSourceAuthorizationSecret(string $id, string $key = 'token'): ?string
    {
        $source = $this->sourceRepository->findById($id);
        if (
            ! $source instanceof Source
            || '' === $source->providerAuthorizationId()
        ) {
            return null;
        }

        $authorization = $this->providerAuthorizationRepository->findByIdInOrganization(
            $source->providerAuthorizationId(),
            $source->organizationId(),
        );
        if (
            ! $authorization instanceof ProviderAuthorization
            || $authorization->provider() !== $source->provider()
        ) {
            return null;
        }

        if (
            'token' === $key
            && null !== $authorization->sourceOAuthAccountId()
            && '' !== $authorization->sourceOAuthAccountId()
        ) {
            $resolved = $this->sourceOAuthTokenResolver->resolveForProviderAuthorization($authorization);

            if ('' !== $resolved['access_token']) {
                return $resolved['access_token'];
            }
        }

        return $this->resolveSecretFromAuthorization($authorization, $key);
    }

    public function resolveAuthorizationSecret(string $id): ?string
    {
        $authorization = $this->providerAuthorizationRepository->findById($id);
        if (! $authorization instanceof ProviderAuthorization) {
            return null;
        }

        return $this->resolveSecretFromAuthorization($authorization, 'token');
    }

    public function resolveAuthorizationEntitySecret(
        ProviderAuthorization $providerAuthorization,
        ?SourceOAuthAccount $sourceOAuthAccount = null
    ): ?string {
        return $this->resolveSecretFromAuthorization($providerAuthorization, 'token', $sourceOAuthAccount);
    }

    public function resolveWebhookSecret(string $id, string $organizationId): ?string
    {
        $source = $this->sourceRepository->findByIdInOrganization(
            $id,
            $organizationId,
        );
        if (! $source instanceof Source) {
            return null;
        }

        $secret = $source->providerConfig()['webhook_secret'] ?? null;
        $secret = is_string($secret) ? $this->sourceWebhookSecrets->decrypt($secret) : '';

        return '' !== $secret ? $secret : null;
    }

    /**
     * Resolves a secret from a provider authorization with OAuth-aware precedence.
     *
     * When the authorization is linked to a SourceOAuthAccount:
     * - Resolves the access token through SourceOAuthTokenResolver
     * - Does not fall back to copied provider authorization configuration
     *
     * When not linked to an OAuth account:
     * - Returns the copied token from provider authorization configuration
     */
    private function resolveSecretFromAuthorization(
        ProviderAuthorization $providerAuthorization,
        string $key = 'token',
        ?SourceOAuthAccount $sourceOAuthAccount = null
    ): ?string {
        $oauthAccountId = $providerAuthorization->sourceOAuthAccountId();

        if (null !== $oauthAccountId && '' !== $oauthAccountId) {
            $resolved = $sourceOAuthAccount instanceof SourceOAuthAccount
                && $sourceOAuthAccount->getIdentifier()->toString() === $oauthAccountId
                    ? $this->sourceOAuthTokenResolver->resolveStoredAccountForProviderAuthorization($sourceOAuthAccount, $providerAuthorization)
                    : $this->sourceOAuthTokenResolver->resolveStoredForProviderAuthorization($providerAuthorization);
            if ('' !== $resolved['access_token']) {
                $this->log('debug', 'Resolved token from SourceOAuthAccount (canonical source)', [
                    'oauth_account_id' => $oauthAccountId,
                    'authorization_id' => $providerAuthorization->getIdentifier()->toString(),
                ]);

                return $resolved['access_token'];
            }

            $this->log('warning', 'SourceOAuthAccount token resolved to an empty value', [
                'oauth_account_id' => $oauthAccountId,
                'authorization_id' => $providerAuthorization->getIdentifier()->toString(),
            ]);

            return null;
        }

        return $this->decryptAuthorizationSecret($providerAuthorization, $key);
    }

    private function decryptAuthorizationSecret(ProviderAuthorization $providerAuthorization, string $key = 'token'): ?string
    {
        return $this->providerAuthorizationSecrets->decryptSecret($providerAuthorization, $key);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, '[SourceSecretResolver] ' . $message, $context);
        }
    }
}
