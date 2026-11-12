<?php

declare(strict_types=1);

use Laminas\ServiceManager\ServiceManager;
use Packio\Application\Security\RateLimiter\FileRateLimiter;

// Load configuration
$config = require __DIR__ . '/config.php';

$dependencies                       = $config['dependencies'];
$dependencies['services']['config'] = $config;

// Build container
$container = new ServiceManager($dependencies);
// Validate final policies and native route overrides before accepting traffic.
$container->get(FileRateLimiter::class);

return $container;
