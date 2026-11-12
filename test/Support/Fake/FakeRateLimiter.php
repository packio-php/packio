<?php

declare(strict_types=1);

namespace PackioTest\Support\Fake;

use Packio\Application\Security\RateLimiter\RateLimiterInterface;

use function array_any;
use function array_shift;

/**
 * Deterministic test fake for rate limiter interactions.
 */
final class FakeRateLimiter implements RateLimiterInterface
{
    /** @var array<int, array<string, int|string>> */
    public array $attempts = [];

    /** @var array<int, array<string, int|string>> */
    public array $checks = [];

    /** @var list<bool> */
    private array $results = [];

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $this->attempts[] = [
            'key'           => $key,
            'max_attempts'  => $maxAttempts,
            'decay_seconds' => $decaySeconds,
        ];

        if ([] !== $this->results) {
            return array_shift($this->results);
        }

        return true;
    }

    public function isExceeded(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $this->checks[] = [
            'key'           => $key,
            'max_attempts'  => $maxAttempts,
            'decay_seconds' => $decaySeconds,
        ];

        if ([] !== $this->results) {
            return ! array_shift($this->results);
        }

        return false;
    }

    public function clear(string $key): void
    {
        // no-op for the fake implementation
    }

    public function queueResult(bool $result): void
    {
        $this->results[] = $result;
    }

    public function queueResults(bool ...$results): void
    {
        foreach ($results as $result) {
            $this->results[] = $result;
        }
    }

    public function wasAttemptedForKey(string $key): bool
    {
        return array_any($this->attempts, static fn (array $attempt): bool => $attempt['key'] === $key);
    }
}
