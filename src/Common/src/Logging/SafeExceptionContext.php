<?php

declare(strict_types=1);

namespace Common\Logging;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function array_map;
use function bin2hex;
use function is_array;
use function is_string;
use function preg_replace;
use function random_bytes;

final class SafeExceptionContext
{
    /** @return array<string, mixed> */
    public static function forRequest(Throwable $throwable, ServerRequestInterface $serverRequest): array
    {
        $requestId = $serverRequest->getAttribute('request_id');

        return self::redactContext([
            'correlation_id' => is_string($requestId) && '' !== $requestId ? $requestId : bin2hex(random_bytes(16)),
            'exception'      => self::exceptionData($throwable),
        ]);
    }

    /** @return array<string, mixed> */
    public static function exception(Throwable $throwable): array
    {
        return self::redactContext(self::exceptionData($throwable));
    }

    public static function redact(string $value): string
    {
        $context = self::redactContext([
            'message' => $value,
        ]);

        return is_string($context['message'] ?? null) ? $context['message'] : '[REDACTED]';
    }

    /** Hides token-like path segments (reset and invite tokens); UUIDs and ordinary slugs stay. */
    public static function redactPath(string $path): string
    {
        return (string) preg_replace(
            '~(?<=/)(?![0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?:/|$))[A-Za-z0-9_-]{20,}(?=/|$)~i',
            '[REDACTED]',
            $path,
        );
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public static function redactContext(array $context): array
    {
        $redacted = LogRedactorFactory::create()->redact($context);

        return is_array($redacted) ? $redacted : [
            'redacted' => '[REDACTED]',
        ];
    }

    /** @return array<string, mixed> */
    private static function exceptionData(Throwable $throwable, int $depth = 0): array
    {
        // Bound projection before handing the diagnostic structure to the redactor.
        if ($depth >= 8) {
            return [
                'message' => '[REDACTED]',
            ];
        }

        return [
            'class'    => $throwable::class,
            'message'  => $throwable->getMessage(),
            'file'     => $throwable->getFile(),
            'line'     => $throwable->getLine(),
            // Never serialize trace arguments or objects: they can contain credentials.
            'trace'    => array_map(static fn (array $frame): array => [
                'file'     => $frame['file'] ?? null,
                'line'     => $frame['line'] ?? null,
                'class'    => $frame['class'] ?? null,
                'function' => $frame['function'],
            ], $throwable->getTrace()),
            'previous' => $throwable->getPrevious() instanceof Throwable ? self::exceptionData($throwable->getPrevious(), $depth + 1) : null,
        ];
    }
}
