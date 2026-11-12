<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Support;

use function base64_encode;
use function hash;
use function random_bytes;
use function rtrim;
use function strtr;

final class OAuthPkce
{
    public static function createCodeVerifier(): string
    {
        return self::base64UrlEncode(random_bytes(32));
    }

    public static function createCodeChallenge(string $codeVerifier): string
    {
        return self::base64UrlEncode(hash('sha256', $codeVerifier, true));
    }

    private static function base64UrlEncode(string $value): string
    {
        return $value
                |> base64_encode(...)
                |> (fn ($x) => strtr($x, '+/', '-_'))
                |> (fn ($x) => rtrim((string) $x, '='));
    }
}
