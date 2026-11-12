<?php

declare(strict_types=1);

namespace Common\Security;

use function trim;

final readonly class StoredSecretResolver
{
    public function __construct(private SecretEncryptorInterface $secretEncryptor) {}

    public function encrypt(string $plaintext): string
    {
        return $this->secretEncryptor->encrypt($plaintext);
    }

    public function decrypt(?string $storedValue): ?string
    {
        if (null === $storedValue || '' === trim($storedValue)) {
            return null;
        }

        if (! $this->secretEncryptor->isEncrypted($storedValue)) {
            return null;
        }

        return $this->secretEncryptor->decrypt($storedValue);
    }

    public function resolve(?string $storedValue): string
    {
        if (null === $storedValue || '' === trim($storedValue)) {
            return '';
        }

        if (! $this->secretEncryptor->isEncrypted($storedValue)) {
            return $storedValue;
        }

        return $this->secretEncryptor->decrypt($storedValue) ?? '';
    }
}
