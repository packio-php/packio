<?php

declare(strict_types=1);

namespace Packio\Source\Api\Exception;

use Throwable;

final readonly class ProviderError
{
    private function __construct(public int $status, public string $code, public string $message) {}

    public static function fromException(Throwable $throwable): self
    {
        $failure = $throwable instanceof ProviderCallException ? $throwable->failure : null;
        if ($throwable instanceof ProviderAuthenticationException) {
            $failure = ProviderFailure::AuthorizationExpired;
        }

        return match ($failure) {
            ProviderFailure::AuthorizationExpired => new self(401, $failure->value, 'Provider authorization is invalid or expired. Please reconnect.'),
            ProviderFailure::AccessDenied         => new self(403, $failure->value, 'Provider access was denied.'),
            ProviderFailure::RateLimited          => new self(429, $failure->value, 'Provider rate limit exceeded. Please try again later.'),
            ProviderFailure::Unavailable          => new self(502, $failure->value, 'Provider is temporarily unavailable.'),
            ProviderFailure::InvalidResponse      => new self(502, $failure->value, 'Provider returned an invalid response.'),
            null                                  => new self(500, 'internal_error', 'Provider resources could not be loaded.'),
        };
    }
}
