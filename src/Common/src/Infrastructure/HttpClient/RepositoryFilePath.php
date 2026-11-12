<?php

declare(strict_types=1);

namespace Common\Infrastructure\HttpClient;

use InvalidArgumentException;

use function array_map;
use function explode;
use function implode;
use function preg_match;
use function rawurlencode;
use function str_contains;
use function trim;

final class RepositoryFilePath
{
    public static function normalize(string $path): string
    {
        if (1 === preg_match('/[\x00-\x1f\x7f\\\?#%]/', $path)) {
            throw new InvalidArgumentException('Repository file path must be a relative path without traversal or URL syntax.');
        }

        if ('' === $path || '' === trim($path)) {
            return 'composer.json';
        }

        $path = trim($path);
        foreach (explode('/', $path) as $segment) {
            if ('' === $segment || '.' === $segment || '..' === $segment || str_contains($segment, ':')) {
                throw new InvalidArgumentException('Repository file path must be a relative path without traversal or URL syntax.');
            }
        }

        return $path;
    }

    public static function urlPath(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', self::normalize($path))));
    }
}
