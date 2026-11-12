<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Request;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOL;

use function array_any;
use function array_key_exists;
use function array_values;
use function filter_var;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function trim;

final readonly class ApiRequestPayload
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function string(array $payload, string $key, mixed $default = null, string ...$aliases): string
    {
        $value = self::value($payload, $key, $default, ...$aliases);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function optionalString(array $payload, string $key, string ...$aliases): ?string
    {
        if (! self::has($payload, $key, ...$aliases)) {
            return null;
        }

        $value = self::string($payload, $key, null, ...$aliases);

        return '' !== $value ? $value : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $default
     *
     * @return list<string>
     */
    public static function stringList(array $payload, string $key, array $default, string ...$aliases): array
    {
        $value = self::value($payload, $key, $default, ...$aliases);
        if (! is_array($value)) {
            return $default;
        }

        $strings = [];
        foreach (array_values($value) as $item) {
            if (! is_string($item)) {
                continue;
            }

            $item = trim($item);
            if ('' !== $item) {
                $strings[] = $item;
            }
        }

        return [] !== $strings ? $strings : $default;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $default
     *
     * @return null|array<string, mixed>
     */
    public static function object(array $payload, string $key, array $default = [], string ...$aliases): ?array
    {
        $value = self::value($payload, $key, $default, ...$aliases);

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function boolean(array $payload, string $key, mixed $default, string ...$aliases): ?bool
    {
        $value = self::value($payload, $key, $default, ...$aliases);
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function nonNegativeInteger(array $payload, string $key, mixed $default, string ...$aliases): ?int
    {
        $value = self::value($payload, $key, $default, ...$aliases);
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (is_string($value) && '' !== trim($value) && (string) (int) $value === trim($value)) {
            $value = (int) $value;

            return $value >= 0 ? $value : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function has(array $payload, string $key, string ...$aliases): bool
    {
        return array_any([$key, ...$aliases], fn ($candidate) => array_key_exists($candidate, $payload));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function value(array $payload, string $key, mixed $default, string ...$aliases): mixed
    {
        foreach ([$key, ...$aliases] as $candidate) {
            if (array_key_exists($candidate, $payload)) {
                return $payload[$candidate];
            }
        }

        return $default;
    }
}
