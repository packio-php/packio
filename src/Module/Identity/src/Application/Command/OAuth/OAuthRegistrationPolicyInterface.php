<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command\OAuth;

use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\ValueObject\OAuthUserData;

interface OAuthRegistrationPolicyInterface
{
    public function allowsRegistration(?UserOAuthAccount $userOAuthAccount, OAuthUserData $oAuthUserData, string $redirectTo): bool;
}
