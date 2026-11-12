<?php

declare(strict_types=1);

namespace Packio\Application\Security\RateLimiter;

use Packio\Application\Security\Http\ClientIpResolverInterface;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function is_array;
use function is_int;

/**
 * Tracks failed API credential checks by client IP. Successful and credential-free
 * requests deliberately do not call this service's recording method. This is a
 * failure throttle, not an in-flight admission limiter: concurrent requests can
 * pass the check before another request records its failed credential.
 */
final readonly class ApiAuthenticationAttemptLimiter
{
    private const string POLICY = 'api_authentication';

    /**
     * @param array<string, mixed> $rateLimitConfig
     */
    public function __construct(
        private RateLimiterInterface $rateLimiter,
        private ClientIpResolverInterface $clientIpResolver,
        private array $rateLimitConfig,
    ) {}

    public function isBlocked(ServerRequestInterface $serverRequest): bool
    {
        [$maxAttempts, $decaySeconds] = $this->policy();

        return $this->rateLimiter->isExceeded($this->key($serverRequest), $maxAttempts, $decaySeconds);
    }

    public function recordFailedCredential(ServerRequestInterface $serverRequest): void
    {
        [$maxAttempts, $decaySeconds] = $this->policy();
        $this->rateLimiter->attempt($this->key($serverRequest), $maxAttempts, $decaySeconds);
    }

    public function retryAfterSeconds(): int
    {
        [, $decaySeconds] = $this->policy();

        return $decaySeconds;
    }

    private function key(ServerRequestInterface $serverRequest): string
    {
        return self::POLICY . ':ip:' . ($this->clientIpResolver->resolve($serverRequest) ?? 'unknown');
    }

    /**
     * @return array{0: positive-int, 1: positive-int}
     */
    private function policy(): array
    {
        $policy       = $this->rateLimitConfig[self::POLICY] ?? null;
        $maxAttempts  = is_array($policy) ? $policy['max_attempts'] ?? null : null;
        $decaySeconds = is_array($policy) ? $policy['decay_seconds'] ?? null : null;

        if (! is_int($maxAttempts) || $maxAttempts < 1 || ! is_int($decaySeconds) || $decaySeconds < 1) {
            throw new UnexpectedValueException('API authentication rate limit policy is invalid.');
        }

        return [$maxAttempts, $decaySeconds];
    }
}
