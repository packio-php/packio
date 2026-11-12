<?php

declare(strict_types=1);

namespace Packio\Application\Security\Turnstile;

interface TurnstileVerifierInterface
{
    public function verify(string $token, ?string $ip = null): bool;
}
