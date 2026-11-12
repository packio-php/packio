<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Security;

use function password_verify;

final class PasswordVerification
{
    // Same bcrypt cost as PASSWORD_DEFAULT; this hash can never authenticate a missing account/code.
    private const string DUMMY_HASH = '$2y$12$EzWnb3nXr51omuzsi7.0d.KaJgYAhG3FJBWLRK/hRr/wYI1Ee0NTS';

    public static function matches(string $value, ?string $hash): bool
    {
        $hasHash = null !== $hash && '' !== $hash;
        $matches = password_verify($value, $hasHash ? $hash : self::DUMMY_HASH);

        return $hasHash && $matches;
    }
}
