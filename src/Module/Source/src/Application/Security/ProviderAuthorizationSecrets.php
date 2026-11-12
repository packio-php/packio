<?php

declare(strict_types=1);

namespace Packio\Source\Application\Security;

use Common\Security\SecretEncryptorInterface;
use Packio\Source\Domain\Entity\ProviderAuthorization;

use function is_string;

final readonly class ProviderAuthorizationSecrets
{
    public function __construct(private SecretEncryptorInterface $secretEncryptor) {}

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    public function encryptConfiguration(array $configuration, string $tokenKey = 'token'): array
    {
        $token = $configuration[$tokenKey] ?? null;
        if (! is_string($token) || '' === $token) {
            return $configuration;
        }

        $configuration[$tokenKey] = $this->secretEncryptor->encrypt($token);

        return $configuration;
    }

    public function decryptToken(ProviderAuthorization $providerAuthorization, string $tokenKey = 'token'): ?string
    {
        return $this->decryptSecret($providerAuthorization, $tokenKey);
    }

    public function decryptSecret(ProviderAuthorization $providerAuthorization, string $tokenKey = 'token'): ?string
    {
        $storedToken = $providerAuthorization->secret($tokenKey);
        if (null === $storedToken || '' === $storedToken) {
            return null;
        }

        if (! $this->secretEncryptor->isEncrypted($storedToken)) {
            return null;
        }

        return $this->secretEncryptor->decrypt($storedToken);
    }
}
