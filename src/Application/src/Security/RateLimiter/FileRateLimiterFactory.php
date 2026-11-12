<?php

declare(strict_types=1);

namespace Packio\Application\Security\RateLimiter;

use Psr\Container\ContainerInterface;

final class FileRateLimiterFactory
{
    public function __invoke(ContainerInterface $container): FileRateLimiter
    {
        /** @var array{security: array{rate_limits: array<string, mixed>}} $config */
        $config     = $container->get('config');
        $rateLimits = $config['security']['rate_limits'];

        return new FileRateLimiter($rateLimits);
    }
}
