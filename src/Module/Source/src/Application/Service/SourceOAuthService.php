<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Cake\Chronos\Chronos;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Port\OrganizationQueryPort;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use RuntimeException;

use function hash;
use function json_encode;
use function trim;

final readonly class SourceOAuthService
{
    public function __construct(
        private OAuthProviderPort $oAuthProviderPort,
        private SourceOAuthAccountRepositoryInterface $sourceOAuthAccountRepository,
        private SourceOAuthAccountSecrets $sourceOAuthAccountSecrets,
        private OrganizationQueryPort $organizationQueryPort,
        private SourceProviderInstanceRepositoryInterface $sourceProviderInstanceRepository,
        private SourceProviderInstanceOAuthConfig $sourceProviderInstanceOAuthConfig,
    ) {}

    public function handleSourceCallback(
        string $provider,
        string $code,
        string $userId,
        string $organizationSlug,
        ?string $codeVerifier = null,
    ): SourceOAuthAccount {
        $oAuthTokenData = $this->oAuthProviderPort->exchangeCodeForToken($provider, $code, $codeVerifier);
        $oAuthUserData  = $this->oAuthProviderPort->fetchUser($provider, $oAuthTokenData->accessToken);
        $scopes         = $this->oAuthProviderPort->sourceScopes($provider);

        // Find organization ID by slug
        $this->resolveOrganizationId($organizationSlug);

        $encryptedTokens = $this->sourceOAuthAccountSecrets->encryptForStorage(
            $oAuthTokenData->accessToken,
            $oAuthTokenData->refreshToken,
        );
        $tokenExpiresAt = null !== $oAuthTokenData->expiresIn
            ? new Chronos('+' . $oAuthTokenData->expiresIn . ' seconds')
            : null;
        $credentialKey = $this->credentialKey($userId, $provider, $oAuthUserData->providerUserId, null);

        $existingAccount = $this->sourceOAuthAccountRepository->findByCredentialIdentity(
            $userId,
            $provider,
            $oAuthUserData->providerUserId,
            null,
        );
        $account = $existingAccount ?? new SourceOAuthAccount(
            userId: $userId,
            provider: $provider,
            providerUserId: $oAuthUserData->providerUserId,
            providerEmail: $oAuthUserData->email,
            providerUsername: $oAuthUserData->username,
            accessToken: $encryptedTokens['access_token'],
            refreshToken: $encryptedTokens['refresh_token'],
            tokenExpiresAt: $tokenExpiresAt,
            scopes: $scopes,
            credentialKey: $credentialKey,
        );
        $account->assignCredentialKey($credentialKey);

        $syncAccount = static function(SourceOAuthAccount $sourceOAuthAccount) use ($oAuthUserData, $scopes, $encryptedTokens, $tokenExpiresAt, $credentialKey): void {
            $sourceOAuthAccount->assignCredentialKey($credentialKey);
            $sourceOAuthAccount->syncAuthorizationContext(
                $oAuthUserData->providerUserId,
                $oAuthUserData->email,
                $oAuthUserData->username,
                $scopes,
            );
            $sourceOAuthAccount->updateTokens(
                $encryptedTokens['access_token'],
                $encryptedTokens['refresh_token'],
                $tokenExpiresAt,
            );
            $sourceOAuthAccount->markAsUsed();
        };

        if ($existingAccount instanceof SourceOAuthAccount) {
            $syncAccount($account);
        }

        return $this->sourceOAuthAccountRepository->saveOrUpdateByCredentialKey($account, $syncAccount);
    }

    public function handleSourceProviderInstanceCallback(
        string $provider,
        string $code,
        string $userId,
        string $organizationSlug,
        string $providerInstanceId,
        ?string $codeVerifier = null,
    ): SourceOAuthAccount {
        $organizationId = $this->resolveOrganizationId($organizationSlug);
        $instance       = $this->sourceProviderInstanceRepository
            ->findActiveByIdInOrganization($providerInstanceId, $organizationId)
        ;

        if (! $instance instanceof SourceProviderInstance) {
            throw new RuntimeException('Source provider instance was not found or is inactive.');
        }

        if ($instance->providerType() !== $provider || ! $instance->supportsOAuth()) {
            throw new RuntimeException('Source provider instance does not support OAuth for this provider.');
        }

        $providerInstanceConfig = $this->sourceProviderInstanceOAuthConfig->forInstance($instance);
        if (null === $providerInstanceConfig) {
            throw new RuntimeException('OAuth client secret is required for source provider instance OAuth.');
        }

        $oAuthTokenData = $this->oAuthProviderPort->exchangeCodeForTokenForProviderInstance(
            $provider,
            $providerInstanceConfig,
            $code,
            $codeVerifier,
        );
        $oAuthUserData = $this->oAuthProviderPort->fetchUserForProviderInstance(
            $provider,
            $providerInstanceConfig,
            $oAuthTokenData->accessToken,
        );
        $scopes = $this->sourceProviderInstanceOAuthConfig->scopes($instance);

        $encryptedTokens = $this->sourceOAuthAccountSecrets->encryptForStorage(
            $oAuthTokenData->accessToken,
            $oAuthTokenData->refreshToken,
        );
        $tokenExpiresAt = null !== $oAuthTokenData->expiresIn
            ? new Chronos('+' . $oAuthTokenData->expiresIn . ' seconds')
            : null;
        $credentialKey = $this->credentialKey($userId, $provider, $oAuthUserData->providerUserId, $providerInstanceId);

        $existingAccount = $this->sourceOAuthAccountRepository->findByCredentialIdentity(
            $userId,
            $provider,
            $oAuthUserData->providerUserId,
            $providerInstanceId,
        );
        $account = $existingAccount ?? new SourceOAuthAccount(
            userId: $userId,
            provider: $provider,
            providerUserId: $oAuthUserData->providerUserId,
            providerEmail: $oAuthUserData->email,
            providerUsername: $oAuthUserData->username,
            accessToken: $encryptedTokens['access_token'],
            refreshToken: $encryptedTokens['refresh_token'],
            tokenExpiresAt: $tokenExpiresAt,
            scopes: $scopes,
            credentialKey: $credentialKey,
        );

        $account->assignProviderInstance($providerInstanceId, $instance->baseUrl());
        $account->assignCredentialKey($credentialKey);

        $syncAccount = static function(SourceOAuthAccount $sourceOAuthAccount) use ($oAuthUserData, $scopes, $encryptedTokens, $tokenExpiresAt, $providerInstanceId, $instance, $credentialKey): void {
            $sourceOAuthAccount->assignCredentialKey($credentialKey);
            $sourceOAuthAccount->syncAuthorizationContext(
                $oAuthUserData->providerUserId,
                $oAuthUserData->email,
                $oAuthUserData->username,
                $scopes,
            );
            $sourceOAuthAccount->updateTokens(
                $encryptedTokens['access_token'],
                $encryptedTokens['refresh_token'],
                $tokenExpiresAt,
            );
            $sourceOAuthAccount->assignProviderInstance($providerInstanceId, $instance->baseUrl());
            $sourceOAuthAccount->markAsUsed();
        };

        if ($existingAccount instanceof SourceOAuthAccount) {
            $syncAccount($account);
        }

        return $this->sourceOAuthAccountRepository->saveOrUpdateByCredentialKey(
            $account,
            $syncAccount,
            $organizationId,
        );
    }

    public function invalidateSourceOAuth(string $sourceOAuthId): void
    {
        $account = $this->sourceOAuthAccountRepository->findById($sourceOAuthId);
        if (! $account instanceof SourceOAuthAccount) {
            return;
        }

        $account->markAsInactive();
        $this->sourceOAuthAccountRepository->save($account);
    }

    /**
     * @return array{valid: bool, error?: string, invalidated: bool}
     */
    public function verifyAndInvalidateSourceOAuth(string $sourceOAuthId): array
    {
        $account = $this->sourceOAuthAccountRepository->findById($sourceOAuthId);

        if (! $account instanceof SourceOAuthAccount) {
            return [
                'valid'       => false,
                'error'       => 'Source OAuth account not found',
                'invalidated' => false,
            ];
        }

        if (! $account->isActive()) {
            return [
                'valid'       => false,
                'error'       => 'Source OAuth account is inactive',
                'invalidated' => false,
            ];
        }

        $accessToken = $this->sourceOAuthAccountSecrets->decryptAccessToken($account);

        if (null === $accessToken || '' === trim($accessToken)) {
            $account->markAsInactive();
            $this->sourceOAuthAccountRepository->save($account);

            return [
                'valid'       => false,
                'error'       => 'Source OAuth token is missing or unreadable. Please reconnect.',
                'invalidated' => true,
            ];
        }

        $tokenValid = $this->oAuthProviderPort->verifyToken($account->provider(), $accessToken);

        if (! $tokenValid) {
            $account->markAsInactive();
            $this->sourceOAuthAccountRepository->save($account);

            return [
                'valid'       => false,
                'error'       => 'Source OAuth token is invalid or expired. Please reconnect.',
                'invalidated' => true,
            ];
        }

        $account->markAsUsed();
        $this->sourceOAuthAccountRepository->save($account);

        return [
            'valid'       => true,
            'invalidated' => false,
        ];
    }

    private function resolveOrganizationId(string $slug): string
    {
        if ('' === $slug) {
            return '';
        }

        $resolved = $this->organizationQueryPort->resolveOrganizationId($slug);

        if (null === $resolved) {
            throw new RuntimeException('Organization not found for source OAuth connection.');
        }

        return $resolved;
    }

    private function credentialKey(string $userId, string $provider, string $providerUserId, ?string $providerInstanceId): string
    {
        return hash('sha256', (string) json_encode([
            'user_id'              => $userId,
            'provider'             => $provider,
            'provider_user_id'     => $providerUserId,
            'provider_instance_id' => '' !== trim((string) $providerInstanceId) ? $providerInstanceId : null,
        ]));
    }
}
