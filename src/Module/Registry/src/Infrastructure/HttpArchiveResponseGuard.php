<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure;

use RuntimeException;

use function preg_match;
use function sprintf;

final readonly class HttpArchiveResponseGuard
{
    /**
     * @param array<int, string> $responseHeaders
     */
    public static function assertSuccessful(string $url, array $responseHeaders): void
    {
        $status = self::statusCode($responseHeaders);

        if (null === $status || $status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf(
                'Failed to download archive from "%s": upstream returned HTTP %s.',
                $url,
                null !== $status ? (string) $status : 'unknown',
            ));
        }
    }

    /**
     * @param array<int, string> $responseHeaders
     */
    private static function statusCode(array $responseHeaders): ?int
    {
        $status = null;

        foreach ($responseHeaders as $responseHeader) {
            if (1 === preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $responseHeader, $matches)) {
                $status = (int) $matches[1];
            }
        }

        return $status;
    }
}
