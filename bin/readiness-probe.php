<?php

declare(strict_types=1);

use Packio\Application\Infrastructure\Readiness\ReadinessProbe;

require \dirname(__DIR__) . '/vendor/autoload.php';

try {
    $decoded = \json_decode(\stream_get_contents(STDIN), flags: JSON_THROW_ON_ERROR);
    if (! \is_object($decoded)) {
        exit(1);
    }

    $config = \get_object_vars($decoded);
    if (! \is_string($config['dsn'] ?? null)
        || ! \array_key_exists('user', $config)
        || ! (null === $config['user'] || \is_string($config['user']))
        || ! \array_key_exists('password', $config)
        || ! (null === $config['password'] || \is_string($config['password']))
        || ! \is_string($config['schema_file'] ?? null)
        || ! \is_string($config['migrations_dir'] ?? null)
        || ! \is_string($config['migrations_table'] ?? null)
        || ! \array_key_exists('archive', $config)
        || ! (null === $config['archive'] || \is_string($config['archive']))) {
        exit(1);
    }

    foreach (['schemas', 'cache', 'settings'] as $key) {
        if (! \is_array($config[$key] ?? null) || ! \array_is_list($config[$key]) || [] !== \array_filter($config[$key], static fn (mixed $value): bool => ! \is_string($value))) {
            exit(1);
        }
    }

    exit(ReadinessProbe::check($config) ? 0 : 1);
} catch (Throwable) {
    exit(1);
}
