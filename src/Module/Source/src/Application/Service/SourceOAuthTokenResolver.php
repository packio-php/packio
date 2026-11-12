<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Cake\Chronos\Chronos;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Domain\Exception\ProviderRefreshRejectedException;
use Packio\Source\Domain\Exception\SourceTokenMissingException;
use Packio\Source\Domain\Exception\SourceTokenRefreshFailedException;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountLockingRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function hash;
use function is_array;
use function str_contains;
use function substr;
use function trim;

/**
 * Refresh-aware token resolver for OAuth-backed source authorizations.
 *
 * This service provides a single entry point for resolving provider access tokens
 * at sync time. It handles:
 * - Detecting OAuth-backed authorizations
 * - Determining if token refresh is needed
 * - Performing transparent token refresh
 * - Persisting rotated credentials
 * - Classifying failures into typed exceptions
 */
final readonly class SourceOAuthTokenResolver
{
    public function __construct(
        private SourceOAuthAccountRepositoryInterface $sourceOAuthAccountRepository,
        private ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository,
        private SourceRepositoryInterface $sourceRepository,
        private SourceOAuthAccountSecrets $sourceOAuthAccountSecrets,
        private OAuthProviderPort $oAuthProviderPort,
        private SourceProviderInstanceRepositoryInterface $sourceProviderInstanceRepository,
        private SourceProviderInstanceOAuthConfig $sourceProviderInstanceOAuthConfig,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Resolves a valid access token for a provider authorization.
     *
     * If the authorization is OAuth-backed and the token is expired,
     * this method will attempt to refresh it transparently.
     *
     * @return array{access_token: string, refreshed: bool, oauth_account_id: ?string}
     *
     * @throws SourceTokenMissingException            when no usable token exists
     * @throws SourceTokenRefreshUnavailableException when refresh is needed but not possible
     * @throws SourceTokenRefreshFailedException      when refresh attempt fails
     */
    public function resolveForAuthorization(string $authorizationId): array
    {
        $authorization = $this->providerAuthorizationRepository->findById($authorizationId);
        if (! $authorization instanceof ProviderAuthorization) {
            throw new SourceTokenMissingException('Provider authorization not found.');
        }

        return $this->resolveForProviderAuthorization($authorization);
    }

    /**
     * Resolves a valid access token for an already scoped provider authorization.
     *
     * Use this when the caller already loaded the authorization through an
     * organization-scoped repository lookup.
     *
     * @return array{access_token: string, refreshed: bool, oauth_account_id: ?string}
     *
     * @throws SourceTokenMissingException            when no usable token exists
     * @throws SourceTokenRefreshUnavailableException when refresh is needed but not possible
     * @throws SourceTokenRefreshFailedException      when refresh attempt fails
     */
    public function resolveForProviderAuthorization(ProviderAuthorization $providerAuthorization): array
    {
        return $this->resolveForAuthorizationEntity($providerAuthorization);
    }

    /**
     * Forces a refresh for an OAuth-backed provider authorization.
     *
     * Maintenance jobs use this to keep refresh tokens alive even when no source
     * sync or webhook registration needs an access token at that moment.
     *
     * @return array{access_token: string, refreshed: bool, oauth_account_id: ?string}
     *
     * @throws SourceTokenMissingException
     * @throws SourceTokenRefreshUnavailableException
     * @throws SourceTokenRefreshFailedException
     */
    public function refreshForProviderAuthorization(ProviderAuthorization $providerAuthorization): array
    {
        $oauthAccountId = $providerAuthorization->sourceOAuthAccountId();

        if (null === $oauthAccountId || '' === $oauthAccountId) {
            throw new SourceTokenRefreshUnavailableException(
                'Authorization is not OAuth-backed. Reauthorization required.',
            );
        }

        $account = $this->sourceOAuthAccountRepository->findById($oauthAccountId);
        if (! $account instanceof SourceOAuthAccount) {
            throw new SourceTokenMissingException('Linked OAuth account not found.');
        }

        if ($this->sourceOAuthAccountRepository instanceof SourceOAuthAccountLockingRepositoryInterface) {
            $lockedResult = $this->sourceOAuthAccountRepository->withLockedById(
                $account->getIdentifier()->toString(),
                fn (SourceOAuthAccount $sourceOAuthAccount): array => $this->refreshLockedOAuthAccount($sourceOAuthAccount, $providerAuthorization),
            );

            if (is_array($lockedResult)) {
                return $lockedResult;
            }

            throw new SourceTokenMissingException('Linked OAuth account not found.');
        }

        return $this->refreshLockedOAuthAccount($account, $providerAuthorization);
    }

    /**
     * Resolves the currently stored access token without refreshing it.
     *
     * This is intended for read-only display paths such as masking credentials.
     *
     * @return array{access_token: string, refreshed: bool, oauth_account_id: ?string}
     *
     * @throws SourceTokenMissingException            when no usable token exists
     * @throws SourceTokenRefreshUnavailableException when the linked account cannot be used
     */
    public function resolveStoredForProviderAuthorization(ProviderAuthorization $providerAuthorization): array
    {
        $oauthAccountId = $providerAuthorization->sourceOAuthAccountId();

        if (null === $oauthAccountId || '' === $oauthAccountId) {
            return [
                'access_token'     => '',
                'refreshed'        => false,
                'oauth_account_id' => null,
            ];
        }

        $account = $this->sourceOAuthAccountRepository->findById($oauthAccountId);
        if (! $account instanceof SourceOAuthAccount) {
            throw new SourceTokenMissingException('Linked OAuth account not found.');
        }

        return $this->resolveStoredFromOAuthAccount($account, $providerAuthorization);
    }

    /**
     * Resolves the currently stored access token from a preloaded OAuth account without refreshing it.
     *
     * @return array{access_token: string, refreshed: bool, oauth_account_id: ?string}
     */
    public function resolveStoredAccountForProviderAuthorization(
        SourceOAuthAccount $sourceOAuthAccount,
        ProviderAuthorization $providerAuthorization
    ): array {
        return $this->resolveStoredFromOAuthAccount($sourceOAuthAccount, $providerAuthorization);
    }

    /**
     * Resolves a valid access token for a source by looking up its provider authorization.
     *
     * @return array{access_token: string, refreshed: bool, oauth_account_id: ?string}
     *
     * @throws SourceTokenMissingException            when no usable token exists
     * @throws SourceTokenRefreshUnavailableException when refresh is needed but not possible
     * @throws SourceTokenRefreshFailedException      when refresh attempt fails
     */
    public function resolveForSource(string $sourceId): array
    {
        $authorization = $this->findAuthorizationForSource($sourceId);
        if (! $authorization instanceof ProviderAuthorization) {
            throw new SourceTokenMissingException('No provider authorization found for source.');
        }

        return $this->resolveForProviderAuthorization($authorization);
    }

    /**
     * Internal resolution logic for a provider authorization entity.
     *
     * @return array{access_token: string, refreshed: bool, oauth_account_id: ?string}
     */
    private function resolveForAuthorizationEntity(ProviderAuthorization $providerAuthorization): array
    {
        $oauthAccountId = $providerAuthorization->sourceOAuthAccountId();

        if (null === $oauthAccountId || '' === $oauthAccountId) {
            $this->log('debug', 'Authorization is not OAuth-backed, no refresh path available', [
                'authorization_id' => $providerAuthorization->getIdentifier()->toString(),
            ]);

            return [
                'access_token'     => '',
                'refreshed'        => false,
                'oauth_account_id' => null,
            ];
        }

        $account = $this->sourceOAuthAccountRepository->findById($oauthAccountId);
        if (! $account instanceof SourceOAuthAccount) {
            throw new SourceTokenMissingException('Linked OAuth account not found.');
        }

        return $this->resolveFromOAuthAccount($account, $providerAuthorization);
    }

    /**
     * Resolves token from an OAuth account, refreshing if necessary.
     *
     * @return array{access_token: string, refreshed: bool, oauth_account_id: ?string}
     */
    private function resolveFromOAuthAccount(SourceOAuthAccount $sourceOAuthAccount, ProviderAuthorization $providerAuthorization): array
    {
        $resolved = $this->resolveStoredFromOAuthAccount($sourceOAuthAccount, $providerAuthorization);

        if (! $this->isTokenExpired($sourceOAuthAccount)) {
            $this->log('debug', 'Token is still valid, no refresh needed', [
                'oauth_account_id' => $sourceOAuthAccount->getIdentifier()->toString(),
            ]);

            return $resolved;
        }

        if ($this->sourceOAuthAccountRepository instanceof SourceOAuthAccountLockingRepositoryInterface) {
            $lockedResult = $this->sourceOAuthAccountRepository->withLockedById(
                $sourceOAuthAccount->getIdentifier()->toString(),
                fn (SourceOAuthAccount $sourceOAuthAccount): array => $this->resolveFromLockedOAuthAccount($sourceOAuthAccount, $providerAuthorization),
            );

            if (is_array($lockedResult)) {
                return $lockedResult;
            }

            throw new SourceTokenMissingException('Linked OAuth account not found.');
        }

        return $this->refreshAndPersist($sourceOAuthAccount, $providerAuthorization);
    }

    /**
     * Re-checks token state under a row lock so rotated refresh tokens are not reused by concurrent workers.
     *
     * @return array{access_token: string, refreshed: bool, oauth_account_id: ?string}
     */
    private function resolveFromLockedOAuthAccount(
        SourceOAuthAccount $sourceOAuthAccount,
        ProviderAuthorization $providerAuthorization
    ): array {
        $resolved = $this->resolveStoredFromOAuthAccount($sourceOAuthAccount, $providerAuthorization);

        if (! $this->isTokenExpired($sourceOAuthAccount)) {
            $this->log('debug', 'Token is still valid after refresh lock, no refresh needed', [
                'oauth_account_id' => $sourceOAuthAccount->getIdentifier()->toString(),
            ]);

            return $resolved;
        }

        return $this->refreshAndPersist($sourceOAuthAccount, $providerAuthorization);
    }

    /**
     * Validates a locked account against the authorization before forced refresh.
     *
     * @return array{access_token: string, refreshed: bool, oauth_account_id: ?string}
     */
    private function refreshLockedOAuthAccount(
        SourceOAuthAccount $sourceOAuthAccount,
        ProviderAuthorization $providerAuthorization
    ): array {
        $this->resolveStoredFromOAuthAccount($sourceOAuthAccount, $providerAuthorization);

        return $this->refreshAndPersist($sourceOAuthAccount, $providerAuthorization);
    }

    /**
     * @return array{access_token: string, refreshed: bool, oauth_account_id: ?string}
     */
    private function resolveStoredFromOAuthAccount(
        SourceOAuthAccount $sourceOAuthAccount,
        ProviderAuthorization $providerAuthorization
    ): array {
        if (! $sourceOAuthAccount->isActive()) {
            throw new SourceTokenRefreshUnavailableException(
                'OAuth account is inactive. Reauthorization required.',
            );
        }

        if ($sourceOAuthAccount->provider() !== $providerAuthorization->provider()) {
            throw new SourceTokenRefreshUnavailableException(
                'OAuth account does not match provider authorization. Reauthorization required.',
            );
        }

        $authorizationProviderInstanceId = trim((string) $providerAuthorization->providerInstanceId());
        $accountProviderInstanceId       = trim((string) $sourceOAuthAccount->providerInstanceId());
        if (
            ('' !== $authorizationProviderInstanceId || '' !== $accountProviderInstanceId)
            && $authorizationProviderInstanceId !== $accountProviderInstanceId
        ) {
            throw new SourceTokenRefreshUnavailableException(
                'OAuth account does not match provider authorization instance. Reauthorization required.',
            );
        }

        $accessToken = $this->sourceOAuthAccountSecrets->decryptAccessToken($sourceOAuthAccount);
        if (null === $accessToken || '' === trim($accessToken)) {
            throw new SourceTokenMissingException('OAuth account has no access token.');
        }

        return [
            'access_token'     => $accessToken,
            'refreshed'        => false,
            'oauth_account_id' => $sourceOAuthAccount->getIdentifier()->toString(),
        ];
    }

    /**
     * Attempts to refresh the token and persist the new credentials.
     *
     * @return array{access_token: string, refreshed: bool, oauth_account_id: ?string}
     */
    private function refreshAndPersist(SourceOAuthAccount $sourceOAuthAccount, ProviderAuthorization $providerAuthorization): array
    {
        $refreshToken = $this->sourceOAuthAccountSecrets->decryptRefreshToken($sourceOAuthAccount);
        if (null === $refreshToken || '' === trim($refreshToken)) {
            $this->log('warning', 'No refresh token available, reauthorization required', [
                'oauth_account_id' => $sourceOAuthAccount->getIdentifier()->toString(),
                'authorization_id' => $providerAuthorization->getIdentifier()->toString(),
            ]);

            throw new SourceTokenRefreshUnavailableException(
                'No refresh token available. Reauthorization required.',
            );
        }

        $this->log('info', 'Attempting token refresh', [
            'oauth_account_id'          => $sourceOAuthAccount->getIdentifier()->toString(),
            'provider'                  => $sourceOAuthAccount->provider(),
            'refresh_token_fingerprint' => $this->tokenFingerprint($refreshToken),
            'token_expires_at'          => $sourceOAuthAccount->tokenExpiresAt()?->toIso8601String(),
        ]);

        $providerInstanceId     = trim((string) $sourceOAuthAccount->providerInstanceId());
        $providerInstanceConfig = '' !== $providerInstanceId
            ? $this->oauthConfigForProviderInstance($sourceOAuthAccount, $providerAuthorization)
            : [];

        try {
            $tokenData = '' !== $providerInstanceId
                ? $this->oAuthProviderPort->refreshAccessTokenForProviderInstance(
                    $sourceOAuthAccount->provider(),
                    $providerInstanceConfig,
                    $refreshToken,
                )
                : $this->oAuthProviderPort->refreshAccessToken($sourceOAuthAccount->provider(), $refreshToken);
            $refreshedRefreshToken = $tokenData->refreshToken;
            if (
                $this->requiresRotatedRefreshToken($sourceOAuthAccount->provider())
                && (null === $refreshedRefreshToken || '' === trim($refreshedRefreshToken))
            ) {
                $this->log('error', 'Token refresh response did not include rotated refresh token', [
                    'oauth_account_id' => $sourceOAuthAccount->getIdentifier()->toString(),
                    'provider'         => $sourceOAuthAccount->provider(),
                ]);

                throw new SourceTokenRefreshFailedException(
                    'Codeberg token refresh response did not include a new refresh token. Reauthorization required.',
                );
            }

            $result = [
                'access_token'  => $tokenData->accessToken,
                'refresh_token' => null !== $refreshedRefreshToken && '' !== trim($refreshedRefreshToken)
                    ? $refreshedRefreshToken
                    : $refreshToken,
                'expires_in'    => $tokenData->expiresIn,
            ];
        } catch (Throwable $e) {
            if ($e instanceof SourceTokenRefreshFailedException) {
                throw $e;
            }

            if (
                $this->isAlreadyUsedRefreshTokenError($sourceOAuthAccount->provider(), $e)
                && is_array($recovered = $this->recoverFromAlreadyUsedRefreshToken($sourceOAuthAccount, $providerAuthorization, $refreshToken))
            ) {
                return $recovered;
            }

            $this->log('error', 'Token refresh failed', [
                'oauth_account_id'          => $sourceOAuthAccount->getIdentifier()->toString(),
                'error'                     => $e->getMessage(),
                'refresh_token_fingerprint' => $this->tokenFingerprint($refreshToken),
            ]);

            throw new SourceTokenRefreshFailedException(
                'Token refresh failed: ' . $e->getMessage(),
                $e->getCode(),
                previous: $e,
                requiresReauthorization: $e instanceof ProviderRefreshRejectedException,
            );
        }

        $encryptedTokens = $this->sourceOAuthAccountSecrets->encryptForStorage(
            $result['access_token'],
            $result['refresh_token'],
        );

        $expiresAt = null !== $result['expires_in']
            ? new Chronos('+' . $result['expires_in'] . ' seconds')
            : null;

        $sourceOAuthAccount->updateTokens(
            $encryptedTokens['access_token'],
            $encryptedTokens['refresh_token'],
            $expiresAt,
        );

        $this->sourceOAuthAccountRepository->save($sourceOAuthAccount);

        $this->log('info', 'Token refreshed successfully', [
            'oauth_account_id'          => $sourceOAuthAccount->getIdentifier()->toString(),
            'refresh_token_fingerprint' => $this->tokenFingerprint($result['refresh_token']),
            'token_expires_at'          => $expiresAt?->toIso8601String(),
        ]);

        return [
            'access_token'     => $result['access_token'],
            'refreshed'        => true,
            'oauth_account_id' => $sourceOAuthAccount->getIdentifier()->toString(),
        ];
    }

    /**
     * Determines if the OAuth account's token is expired or about to expire.
     *
     * Uses a 60-second buffer to avoid edge cases.
     */
    private function isTokenExpired(SourceOAuthAccount $sourceOAuthAccount): bool
    {
        $expiresAt = $sourceOAuthAccount->tokenExpiresAt();
        if (! $expiresAt instanceof Chronos) {
            return false;
        }

        $buffer = new Chronos('+60 seconds');

        return $expiresAt <= $buffer;
    }

    private function requiresRotatedRefreshToken(string $provider): bool
    {
        return 'codeberg' === $provider;
    }

    private function isAlreadyUsedRefreshTokenError(string $provider, Throwable $throwable): bool
    {
        return 'codeberg' === $provider
            && str_contains($throwable->getMessage(), 'token was already used');
    }

    /**
     * @return null|array{access_token: string, refreshed: bool, oauth_account_id: ?string}
     */
    private function recoverFromAlreadyUsedRefreshToken(
        SourceOAuthAccount $sourceOAuthAccount,
        ProviderAuthorization $providerAuthorization,
        string $usedRefreshToken
    ): ?array {
        $currentAccount = $this->sourceOAuthAccountRepository->findById($sourceOAuthAccount->getIdentifier()->toString());
        if (! $currentAccount instanceof SourceOAuthAccount) {
            return null;
        }

        $currentRefreshToken = $this->sourceOAuthAccountSecrets->decryptRefreshToken($currentAccount);
        if ($currentRefreshToken === $usedRefreshToken || $this->isTokenExpired($currentAccount)) {
            $this->log('warning', 'Codeberg refresh token was already used and no newer stored token is available', [
                'oauth_account_id'                  => $sourceOAuthAccount->getIdentifier()->toString(),
                'refresh_token_fingerprint'         => $this->tokenFingerprint($usedRefreshToken),
                'current_refresh_token_fingerprint' => $this->tokenFingerprint($currentRefreshToken),
                'token_expires_at'                  => $currentAccount->tokenExpiresAt()?->toIso8601String(),
            ]);

            return null;
        }

        $this->log('info', 'Recovered from already-used Codeberg refresh token with newer stored token', [
            'oauth_account_id'                  => $sourceOAuthAccount->getIdentifier()->toString(),
            'used_refresh_token_fingerprint'    => $this->tokenFingerprint($usedRefreshToken),
            'current_refresh_token_fingerprint' => $this->tokenFingerprint($currentRefreshToken),
            'token_expires_at'                  => $currentAccount->tokenExpiresAt()?->toIso8601String(),
        ]);

        return $this->resolveStoredFromOAuthAccount($currentAccount, $providerAuthorization);
    }

    private function tokenFingerprint(?string $token): ?string
    {
        if (null === $token || '' === trim($token)) {
            return null;
        }

        return substr(hash('sha256', $token), 0, 12);
    }

    private function findAuthorizationForSource(string $sourceId): ?ProviderAuthorization
    {
        $source = $this->sourceRepository->findById($sourceId);
        if (! $source instanceof Source || '' === $source->providerAuthorizationId()) {
            return null;
        }

        return $this->providerAuthorizationRepository->findByIdInOrganization(
            $source->providerAuthorizationId(),
            $source->organizationId(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function oauthConfigForProviderInstance(
        SourceOAuthAccount $sourceOAuthAccount,
        ProviderAuthorization $providerAuthorization
    ): array {
        $providerInstanceId = trim((string) $sourceOAuthAccount->providerInstanceId());
        $instance           = $this->sourceProviderInstanceRepository
            ->findActiveByIdInOrganization($providerInstanceId, $providerAuthorization->organizationId())
        ;
        if (! $instance instanceof SourceProviderInstance) {
            throw new SourceTokenRefreshUnavailableException('Source provider instance is inactive or missing. Reauthorization required.');
        }

        $config = $this->sourceProviderInstanceOAuthConfig->forInstance($instance);
        if (null === $config) {
            throw new SourceTokenRefreshUnavailableException('Source provider instance OAuth client secret is missing. Reauthorization required.');
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, '[SourceOAuthTokenResolver] ' . $message, $context);
        }
    }
}
