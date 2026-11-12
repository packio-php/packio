<?php

declare(strict_types=1);

namespace Common\Security;

use RuntimeException;

use function base64_decode;
use function base64_encode;
use function hash_hkdf;
use function is_string;
use function random_bytes;
use function sodium_crypto_secretbox;
use function sodium_crypto_secretbox_open;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

final readonly class SodiumSecretEncryptor implements SecretEncryptorInterface
{
    private const string PREFIX = 'secretbox:v1:';
    private string $key;

    public function __construct(string $keyMaterial, bool $productionMode = false, string $fallbackContext = '')
    {
        $resolvedKeyMaterial = trim($keyMaterial);
        if ('' === $resolvedKeyMaterial) {
            if ($productionMode) {
                throw new RuntimeException('APP_SECRET_ENCRYPTION_KEY must be configured in production.');
            }
            $resolvedKeyMaterial = 'packio-dev-secret:' . $fallbackContext;
        }

        $decoded = str_starts_with($resolvedKeyMaterial, 'base64:')
            ? base64_decode(substr($resolvedKeyMaterial, 7), true)
            : $resolvedKeyMaterial;
        if (! is_string($decoded) || '' === $decoded) {
            throw new RuntimeException('Secret encryption key is invalid.');
        }

        $this->key = hash_hkdf('sha256', $decoded, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'packio.provider-authorization');
    }

    public function encrypt(string $plaintext): string
    {
        $nonce      = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return self::PREFIX . base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $ciphertext): ?string
    {
        if (! $this->isEncrypted($ciphertext)) {
            return $ciphertext;
        }
        $decoded = base64_decode(substr($ciphertext, strlen(self::PREFIX)), true);
        if (
            ! is_string($decoded)
            || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES
        ) {
            return null;
        }
        $nonce     = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $payload   = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($payload, $nonce, $this->key);

        return false === $plaintext ? null : $plaintext;
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }
}
