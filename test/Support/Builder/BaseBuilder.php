<?php

declare(strict_types=1);

namespace PackioTest\Support\Builder;

use function array_merge;
use function bin2hex;
use function random_bytes;

abstract class BaseBuilder
{
    /**
     * @param array<string, mixed> $overrides
     */
    final protected function value(string $key, mixed $default, array $overrides): mixed
    {
        return $overrides[$key] ?? $default;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    final protected function merge(array $base, array $overrides): array
    {
        return array_merge($base, $overrides);
    }

    final protected static function uniqueToken(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(8));
    }
}
