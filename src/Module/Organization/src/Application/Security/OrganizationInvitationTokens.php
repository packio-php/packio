<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Security;

use Common\Security\SecretEncryptorInterface;

final readonly class OrganizationInvitationTokens
{
    public function __construct(private SecretEncryptorInterface $secretEncryptor) {}

    public function encrypt(string $token): string
    {
        if ('' === $token || $this->secretEncryptor->isEncrypted($token)) {
            return $token;
        }

        return $this->secretEncryptor->encrypt($token);
    }

    /**
     * Legacy plaintext is returned as is; a value that cannot be decrypted (e.g. after key rotation) becomes ''.
     */
    public function decrypt(string $stored): string
    {
        if (! $this->secretEncryptor->isEncrypted($stored)) {
            return $stored;
        }

        return $this->secretEncryptor->decrypt($stored) ?? '';
    }
}
