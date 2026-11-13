<?php

declare(strict_types=1);

use Common\ConfigProvider;
use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;

require __DIR__ . '/env.php';

// To enable or disable caching, set the `ConfigAggregator::ENABLE_CACHE` boolean in
// `config/autoload/local.php`.
$cacheConfig = [
    'config_cache_path' => 'data/cache/config-cache.php',
];

$aggregator = new ConfigAggregator([
    Sirix\InertiaPsr15MezzioFlash\ConfigProvider::class,
    Mezzio\Flash\ConfigProvider::class,
    Sirix\SentryPsr\ConfigProvider::class,
    Sirix\Redaction\Bridge\Mezzio\ConfigProvider::class,
    Webware\Event\ConfigProvider::class,
    Laminas\ServiceManager\ConfigProvider::class,
    Mezzio\Session\Ext\ConfigProvider::class,
    Mezzio\Twig\ConfigProvider::class,
    Sirix\TwigViteExtension\ConfigProvider::class,
    Sirix\InertiaPsr15\ConfigProvider::class,
    Mezzio\Session\ConfigProvider::class,
    Sirix\Mezzio\Rbac\ConfigProvider::class,
    Sirix\Mezzio\Authentication\ConfigProvider::class,
    Sirix\Mezzio\Routing\Attributes\ConfigProvider::class,
    Sirix\Mezzio\Valinor\ConfigProvider::class,
    Sirix\Mezzio\Router\RadixRouter\ConfigProvider::class,
    Sirix\Monolog\ConfigProvider::class,
    Mezzio\ConfigProvider::class,
    Mezzio\Cors\ConfigProvider::class,
    Sirix\Cycle\ConfigProvider::class,
    Sirix\Config\ConfigProvider::class,
    Mezzio\Helper\ConfigProvider::class,
    Laminas\HttpHandlerRunner\ConfigProvider::class,
    // Include cache configuration
    new ArrayProvider($cacheConfig),
    Mezzio\Router\ConfigProvider::class,
    Laminas\Diactoros\ConfigProvider::class,
    // Swoole config to overwrite some services (if installed)
    \class_exists(Mezzio\Swoole\ConfigProvider::class)
        ? Mezzio\Swoole\ConfigProvider::class
        : function(): array {
            return [];
        },
    \class_exists(Mezzio\Tooling\ConfigProvider::class)
        ? Mezzio\Tooling\ConfigProvider::class
        : function(): array {
            return [];
        },
    // module config
    ConfigProvider::class,
    Packio\Application\ConfigProvider::class,
    Packio\Access\ConfigProvider::class,
    Packio\Audit\ConfigProvider::class,
    Packio\Webhook\ConfigProvider::class,
    Packio\Source\ConfigProvider::class,
    Packio\Organization\ConfigProvider::class,
    Packio\Identity\ConfigProvider::class,
    Packio\Registry\ConfigProvider::class,
    Packio\Release\ConfigProvider::class,
    Packio\Package\ConfigProvider::class,
    Packio\Admin\ConfigProvider::class,
    Packio\Worker\ConfigProvider::class,
    Packio\Publication\ConfigProvider::class,
    Packio\Rest\ConfigProvider::class,
    // Load application config in a pre-defined order in such a way that local settings
    // overwrite global settings. (Loaded as first to last):
    //   - `global.php`
    //   - `*.global.php`
    //   - `local.php`
    //   - `*.local.php`
    new PhpFileProvider(\realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
    // Load development config if it exists
    new PhpFileProvider(\realpath(__DIR__) . '/development.config.php'),
], $cacheConfig['config_cache_path']);

return $aggregator->getMergedConfig();
