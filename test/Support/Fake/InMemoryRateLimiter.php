<?php

declare(strict_types=1);

namespace PackioTest\Support\Fake;

use Packio\Application\Security\RateLimiter\RateLimiterInterface;

use function array_filter;
use function array_values;
use function count;
use function time;

final class InMemoryRateLimiter implements RateLimiterInterface
{
    /** @var array<string, list<int>> */
    private array $hits = [];

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $now    = time();
        $window = $now - $decaySeconds;

        $this->hits[$key] ??= [];

        // Remove expired timestamps (sliding window)
        $this->hits[$key] = array_values(
            array_filter(
                $this->hits[$key],
                static fn (int $t): bool => $t > $window,
            ),
        );

        // Check limit before adding
        if (count($this->hits[$key]) >= $maxAttempts) {
            return false; // blocked
        }

        $this->hits[$key][] = $now;

        return true; // allowed
    }

    public function isExceeded(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $this->prune($key, $decaySeconds);

        return count($this->hits[$key]) >= $maxAttempts;
    }

    public function clear(string $key): void
    {
        unset($this->hits[$key]);
    }

    private function prune(string $key, int $decaySeconds): void
    {
        $window = time() - $decaySeconds;

        $this->hits[$key] = array_values(
            array_filter(
                $this->hits[$key] ?? [],
                static fn (int $timestamp): bool => $timestamp > $window,
            ),
        );
    }
}
