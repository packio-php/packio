<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command\OAuth;

use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthUserData;

interface OAuthAccountLinkerInterface
{
    /** @param list<string> $scopes */
    public function persistOAuthAccount(
        string $userId,
        OAuthUserData $oAuthUserData,
        OAuthProvider $oAuthProvider,
        array $scopes = []
    ): void;
}
