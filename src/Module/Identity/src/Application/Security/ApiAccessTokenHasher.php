<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Security;

use RuntimeException;

use function hash_hkdf;
use function hash_hmac;
use function trim;

final readonly class ApiAccessTokenHasher
{
    private string $key;

    public function __construct(string $keyMaterial, bool $productionMode = false)
    {
        $resolvedKeyMaterial = trim($keyMaterial);
        if ('' === $resolvedKeyMaterial) {
            if ($productionMode) {
                throw new RuntimeException('APP_SECRET_ENCRYPTION_KEY must be configured in production.');
            }

            $resolvedKeyMaterial = 'packio-dev-api-access-token';
        }

        $this->key = hash_hkdf('sha256', $resolvedKeyMaterial, 32, 'packio.api-access-token');
    }

    public function hash(string $plainTextToken): string
    {
        return hash_hmac('sha256', $plainTextToken, $this->key);
    }
}
