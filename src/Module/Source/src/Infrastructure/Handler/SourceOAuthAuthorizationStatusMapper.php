<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Packio\Source\Application\Exception\SourceOAuthAuthorizationException;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipStatus;

final readonly class SourceOAuthAuthorizationStatusMapper
{
    public static function forSourceOAuthException(SourceOAuthAuthorizationException $sourceOAuthAuthorizationException): int
    {
        return match ($sourceOAuthAuthorizationException->status) {
            SourceOAuthAccountOwnershipStatus::NotFound         => 404,
            SourceOAuthAccountOwnershipStatus::TokenUnavailable => 401,
            SourceOAuthAccountOwnershipStatus::Unavailable      => 403,
            SourceOAuthAccountOwnershipStatus::Allowed          => 200,
        };
    }
}
