<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command;

use Packio\Identity\Application\Result\IdentityUserResult;

interface IdentityCredentialsInterface
{
    public function authenticateCredentials(string $email, string $password): ?IdentityUserResult;
}
