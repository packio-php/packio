<?php

declare(strict_types=1);

namespace PackioTest\Support\Fake;

use Common\Security\SecretEncryptorInterface;

use function str_starts_with;
use function substr;

final class SecretEncryptorStub implements SecretEncryptorInterface
{
    private const string PREFIX = 'enc:';

    public function encrypt(string $plaintext): string
    {
        return self::PREFIX . $plaintext;
    }

    public function decrypt(string $ciphertext): ?string
    {
        if (! $this->isEncrypted($ciphertext)) {
            return null;
        }

        return substr($ciphertext, 4);
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }
}
