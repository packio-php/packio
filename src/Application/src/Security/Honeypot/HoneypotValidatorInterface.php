<?php

declare(strict_types=1);

namespace Packio\Application\Security\Honeypot;

use Psr\Http\Message\ServerRequestInterface;

interface HoneypotValidatorInterface
{
    public function validate(ServerRequestInterface $serverRequest): bool;
}
