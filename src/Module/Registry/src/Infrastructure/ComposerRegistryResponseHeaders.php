<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure;

use Psr\Http\Message\ResponseInterface;

use function str_starts_with;

final readonly class ComposerRegistryResponseHeaders
{
    public static function forPath(ResponseInterface $response, string $path): ResponseInterface
    {
        $response = $response->withoutHeader('Set-Cookie');

        if (str_starts_with($path, '/dist/')) {
            return self::archive($response);
        }

        return self::metadata($response);
    }

    public static function metadata(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader('Cache-Control', 'private, max-age=60, must-revalidate')
            ->withoutHeader('Pragma')
            ->withoutHeader('Expires')
        ;
    }

    public static function archive(ResponseInterface $response, bool $immutable = true): ResponseInterface
    {
        return $response
            ->withHeader(
                'Cache-Control',
                $immutable
                    ? 'private, max-age=31536000, immutable'
                    : 'private, max-age=60, must-revalidate',
            )
            ->withoutHeader('Pragma')
            ->withoutHeader('Expires')
        ;
    }
}
