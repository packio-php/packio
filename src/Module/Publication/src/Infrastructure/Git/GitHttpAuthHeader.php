<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Git;

use function base64_encode;
use function sprintf;

final readonly class GitHttpAuthHeader
{
    public static function basic(string $username, string $password): string
    {
        return sprintf('Authorization: Basic %s', base64_encode($username . ':' . $password));
    }

    public static function bearer(string $token): string
    {
        return sprintf('Authorization: Bearer %s', $token);
    }

    public static function token(string $token): string
    {
        return sprintf('Authorization: token %s', $token);
    }
}
