<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Security;

use function in_array;
use function preg_match;

final class ApiAccessTokenScopes
{
    private const array GLOBAL = ['read', 'write', 'admin', '*'];

    public static function isKnown(string $scope): bool
    {
        return in_array($scope, self::GLOBAL, true)
            || 1 === preg_match('/^(members|organizations|packages|releases|sources|tokens|webhooks):(read|write|\*)$/', $scope);
    }
}
