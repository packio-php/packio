<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Http;

use Psr\Http\Message\ServerRequestInterface;

use function str_starts_with;

final class ApiPathMatcher
{
    public static function isApiRequest(ServerRequestInterface $serverRequest): bool
    {
        return self::isApiPath($serverRequest->getUri()->getPath());
    }

    public static function isApiPath(string $path): bool
    {
        return '/api' === $path || str_starts_with($path, '/api/');
    }
}
