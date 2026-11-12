<?php

declare(strict_types=1);

namespace Packio\Source\Application\Security;

use Common\Security\SecretEncryptorInterface;

final readonly class SourceProviderInstanceSecrets
{
    public function __construct(private SecretEncryptorInterface $secretEncryptor) {}

    public function encryptClientSecret(?string $clientSecret): ?string
    {
        if (null === $clientSecret || '' === $clientSecret) {
            return null;
        }

        return $this->secretEncryptor->encrypt($clientSecret);
    }

    public function decryptClientSecret(?string $clientSecret): ?string
    {
        if (null === $clientSecret || '' === $clientSecret) {
            return null;
        }

        return $this->secretEncryptor->decrypt($clientSecret);
    }
}
