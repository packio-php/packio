<?php

declare(strict_types=1);

use Sirix\Mezzio\Router\Enum\CacheConfig;

\chdir(__DIR__ . '/..');

require 'vendor/autoload.php';

/** @var array<string, mixed> $config */
$isTest     = 'test' === \getenv('APP_ENV');
$configPath = $isTest ? \getenv('PACKIO_ROUTE_CACHE_CONFIG') ?: 'config/config.php' : 'config/config.php';
$config     = require $configPath;

$caches = [];

$routingAttributesCache = $config['routing_attributes']['cache'] ?? null;
if (\is_array($routingAttributesCache)
    && true === ($routingAttributesCache['enabled'] ?? false)
    && \is_string($routingAttributesCache['file'] ?? null)
    && '' !== $routingAttributesCache['file']
) {
    $caches[] = [
        'name' => 'routing attributes',
        'file' => $routingAttributesCache['file'],
    ];
}

$routerConfig     = $config['router'] ?? null;
$radixCacheConfig = \is_array($routerConfig) && \is_array($routerConfig['radix'] ?? null) ? $routerConfig['radix'] : null;
if (\is_array($radixCacheConfig)
    && true === ($radixCacheConfig[CacheConfig::Enabled->value] ?? false)
    && \is_string($radixCacheConfig[CacheConfig::File->value] ?? null)
    && '' !== $radixCacheConfig[CacheConfig::File->value]
) {
    $caches[] = [
        'name' => 'radix router',
        'file' => $radixCacheConfig[CacheConfig::File->value],
    ];
}

if ([] === $caches) {
    echo 'Compiled route caches are disabled; nothing to refresh' . PHP_EOL;

    exit(0);
}

foreach ($caches as ['name' => $name, 'file' => $cacheFile]) {
    if (\is_link($cacheFile) || (\file_exists($cacheFile) && ! \is_file($cacheFile))) {
        \printf("%s cache '%s' must be a regular file%s", $name, $cacheFile, PHP_EOL);

        exit(1);
    }

    if (\file_exists($cacheFile) && false === \unlink($cacheFile)) {
        \printf("Unable to remove %s cache '%s'%s", $name, $cacheFile, PHP_EOL);

        exit(1);
    }
}

$warmupCommand = $isTest
    ? \getenv('PACKIO_ROUTE_CACHE_WARMUP_COMMAND') ?: PHP_BINARY . ' vendor/bin/laminas routing-attributes:cache:warmup'
    : PHP_BINARY . ' vendor/bin/laminas routing-attributes:cache:warmup';

$warmingUpRoutingAttributesCache = \array_any($caches, static fn (array $cache): bool => 'routing attributes' === $cache['name']);

if (! $warmingUpRoutingAttributesCache) {
    echo 'Radix router cache removed; no routing attributes cache to warm up' . PHP_EOL;

    exit(0);
}

\passthru($warmupCommand, $exitCode);

exit($exitCode);
