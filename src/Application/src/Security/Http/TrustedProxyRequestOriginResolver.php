<?php

declare(strict_types=1);

namespace Packio\Application\Security\Http;

use Psr\Http\Message\ServerRequestInterface;

use function ctype_digit;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function parse_url;
use function strtolower;
use function trim;

final readonly class TrustedProxyRequestOriginResolver
{
    public function __construct(private TrustedProxyMatcher $trustedProxyMatcher = new TrustedProxyMatcher()) {}

    /** @return array{scheme: string, host: string, port: null|int} */
    public function resolve(ServerRequestInterface $serverRequest): array
    {
        $uri    = $serverRequest->getUri();
        $origin = [
            'scheme' => strtolower($uri->getScheme()),
            'host'   => strtolower($uri->getHost()),
            'port'   => $uri->getPort(),
        ];
        if (! $this->trustedProxyMatcher->isTrustedRequestProxy($serverRequest)) {
            return $origin;
        }

        $forwardedScheme = $this->firstHeaderValue($serverRequest, 'X-Forwarded-Proto');
        if (null !== $forwardedScheme && in_array(strtolower($forwardedScheme), ['http', 'https'], true)) {
            $origin['scheme'] = strtolower($forwardedScheme);
        }

        $forwardedHost = $this->firstHeaderValue($serverRequest, 'X-Forwarded-Host');
        if (null !== $forwardedHost) {
            $parts = parse_url('//' . $forwardedHost);
            if (is_array($parts) && isset($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])) {
                $origin['host'] = strtolower((string) $parts['host']);
                $origin['port'] = isset($parts['port']) ? (int) $parts['port'] : null;
            }
        }

        $forwardedPort = $this->firstHeaderValue($serverRequest, 'X-Forwarded-Port');
        if (null !== $forwardedPort && ctype_digit($forwardedPort) && (int) $forwardedPort > 0 && (int) $forwardedPort <= 65535) {
            $origin['port'] = (int) $forwardedPort;
        }

        return $origin;
    }

    public function matchesRequestOrigin(ServerRequestInterface $serverRequest, mixed $source, bool $allowsPath): bool
    {
        if (! is_string($source) || '' === $source) {
            return false;
        }

        $parts = parse_url($source);
        if (! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass'])
            || (! $allowsPath && isset($parts['path']) && '/' !== $parts['path'])
        ) {
            return false;
        }

        $origin = $this->resolve($serverRequest);

        return strtolower((string) $parts['scheme']) === $origin['scheme']
            && strtolower((string) $parts['host']) === $origin['host']
            && $this->effectivePort((string) $parts['scheme'], isset($parts['port']) ? (int) $parts['port'] : null)
                === $this->effectivePort($origin['scheme'], $origin['port']);
    }

    private function effectivePort(string $scheme, ?int $port): ?int
    {
        if (null !== $port) {
            return $port;
        }

        return match (strtolower($scheme)) {
            'http'  => 80,
            'https' => 443,
            default => null,
        };
    }

    private function firstHeaderValue(ServerRequestInterface $serverRequest, string $name): ?string
    {
        $values = $serverRequest->getHeader($name);
        if ([] === $values) {
            return null;
        }

        $value = trim(explode(',', $values[0], 2)[0]);

        return '' === $value ? null : $value;
    }
}
