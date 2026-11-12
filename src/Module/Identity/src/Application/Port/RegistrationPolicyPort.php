<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Port;

interface RegistrationPolicyPort
{
    public function allowsRegistration(string $redirectTo): bool;
}
