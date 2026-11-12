<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command\OAuth;

use Packio\Identity\Application\Result\OAuthUserResolution;
use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\ValueObject\OAuthUserData;

interface OAuthUserResolverInterface
{
    public function resolveUser(
        ?UserOAuthAccount $userOAuthAccount,
        OAuthUserData $oAuthUserData
    ): OAuthUserResolution;
}
