<?php

declare(strict_types=1);

namespace Packio\Application\Security\Http;

use Psr\Http\Message\ServerRequestInterface;

interface ClientIpResolverInterface
{
    public function resolve(ServerRequestInterface $serverRequest): ?string;
}
