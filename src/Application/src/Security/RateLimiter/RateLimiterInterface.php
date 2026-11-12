<?php

declare(strict_types=1);

namespace Packio\Application\Security\RateLimiter;

interface RateLimiterInterface
{
    /**
     * Return whether a key has already reached its limit without recording a hit.
     *
     * Implementations must fail closed: an unavailable limiter is considered exceeded.
     */
    public function isExceeded(string $key, int $maxAttempts, int $decaySeconds): bool;

    /**
     * Record an attempt and return whether it is allowed.
     *
     * Returns true if the attempt is allowed (under the limit).
     * Returns false if the attempt is blocked (limit exceeded).
     * The hit is NOT recorded when the limit is already exceeded.
     *
     * @param string $key          Unique rate limit key (e.g. 'login:127.0.0.1')
     * @param int    $maxAttempts  Maximum number of allowed attempts in the window
     * @param int    $decaySeconds Length of the sliding window in seconds
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool;

    /**
     * Clear all recorded hits for the given key.
     */
    public function clear(string $key): void;
}
