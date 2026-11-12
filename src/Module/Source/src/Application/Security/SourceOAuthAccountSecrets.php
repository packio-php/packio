<?php

declare(strict_types=1);

namespace Packio\Source\Application\Security;

use Common\Security\SecretEncryptorInterface;
use Packio\Source\Domain\Entity\SourceOAuthAccount;

final readonly class SourceOAuthAccountSecrets
{
    public function __construct(private SecretEncryptorInterface $secretEncryptor) {}

    public function encryptAccessToken(string $accessToken): string
    {
        return $this->secretEncryptor->encrypt($accessToken);
    }

    public function encryptRefreshToken(?string $refreshToken): ?string
    {
        if (null === $refreshToken || '' === $refreshToken) {
            return $refreshToken;
        }

        return $this->secretEncryptor->encrypt($refreshToken);
    }

    /**
     * @return array{access_token: string, refresh_token: ?string}
     */
    public function encryptForStorage(string $accessToken, ?string $refreshToken): array
    {
        return [
            'access_token'  => $this->encryptAccessToken($accessToken),
            'refresh_token' => $this->encryptRefreshToken($refreshToken),
        ];
    }

    public function decryptAccessToken(SourceOAuthAccount $sourceOAuthAccount): ?string
    {
        return $this->decryptRequired($sourceOAuthAccount->storedAccessToken());
    }

    public function decryptRefreshToken(SourceOAuthAccount $sourceOAuthAccount): ?string
    {
        $refreshToken = $sourceOAuthAccount->storedRefreshToken();
        if (null === $refreshToken || '' === $refreshToken) {
            return $refreshToken;
        }

        return $this->decryptRequired($refreshToken);
    }

    private function decryptRequired(string $storedValue): ?string
    {
        if (! $this->secretEncryptor->isEncrypted($storedValue)) {
            return null;
        }

        return $this->secretEncryptor->decrypt($storedValue);
    }
}
