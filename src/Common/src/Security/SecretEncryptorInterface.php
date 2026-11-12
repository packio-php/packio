<?php

declare(strict_types=1);

namespace Common\Security;

interface SecretEncryptorInterface
{
    public function encrypt(string $plaintext): string;

    public function decrypt(string $ciphertext): ?string;

    public function isEncrypted(string $value): bool;
}
