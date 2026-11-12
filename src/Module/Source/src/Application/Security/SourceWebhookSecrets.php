<?php

declare(strict_types=1);

namespace Packio\Source\Application\Security;

use Common\Security\SecretEncryptorInterface;

use function is_string;

final readonly class SourceWebhookSecrets
{
    private const string KEY = 'webhook_secret';

    public function __construct(private SecretEncryptorInterface $secretEncryptor) {}

    /**
     * @param array<string, mixed> $providerConfig
     *
     * @return array<string, mixed>
     */
    public function encryptConfiguration(array $providerConfig): array
    {
        $secret = $providerConfig[self::KEY] ?? null;
        if (! is_string($secret) || '' === $secret || $this->secretEncryptor->isEncrypted($secret)) {
            return $providerConfig;
        }

        $providerConfig[self::KEY] = $this->secretEncryptor->encrypt($secret);

        return $providerConfig;
    }

    /**
     * Legacy plaintext is returned as is; a value that cannot be decrypted (e.g. after key rotation) becomes ''.
     */
    public function decrypt(?string $stored): string
    {
        if (null === $stored || '' === $stored || ! $this->secretEncryptor->isEncrypted($stored)) {
            return (string) $stored;
        }

        return $this->secretEncryptor->decrypt($stored) ?? '';
    }

    /**
     * @param array<string, mixed> $providerConfig
     *
     * @return array<string, mixed>
     */
    public function decryptConfiguration(array $providerConfig): array
    {
        if (isset($providerConfig[self::KEY]) && is_string($providerConfig[self::KEY])) {
            $providerConfig[self::KEY] = $this->decrypt($providerConfig[self::KEY]);
        }

        return $providerConfig;
    }
}
