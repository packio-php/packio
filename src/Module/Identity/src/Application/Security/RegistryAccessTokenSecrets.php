<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Security;

use Common\Security\SecretEncryptorInterface;
use Packio\Identity\Domain\Entity\RegistryAccessToken;

final readonly class RegistryAccessTokenSecrets
{
    public function __construct(private SecretEncryptorInterface $secretEncryptor) {}

    public function encryptToken(string $plainTextToken): string
    {
        return $this->secretEncryptor->encrypt($plainTextToken);
    }

    public function decryptToken(RegistryAccessToken $registryAccessToken): ?string
    {
        $storedToken = $registryAccessToken->storedEncryptedToken();
        if ('' === $storedToken) {
            return null;
        }

        if (! $this->secretEncryptor->isEncrypted($storedToken)) {
            return null;
        }

        return $this->secretEncryptor->decrypt($storedToken);
    }
}
