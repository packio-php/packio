<?php

declare(strict_types=1);

namespace Packio\Application\Security\Http;

use const FILTER_VALIDATE_IP;

use Psr\Http\Message\ServerRequestInterface;

use function array_any;
use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function filter_var;
use function in_array;
use function inet_pton;
use function intdiv;
use function is_array;
use function is_string;
use function ord;
use function preg_match;
use function str_contains;
use function strlen;
use function substr;
use function trim;

final readonly class TrustedProxyMatcher
{
    /** @param array<string, mixed> $clientIpConfig */
    public function __construct(private array $clientIpConfig = []) {}

    public function isTrustedRequestProxy(ServerRequestInterface $serverRequest): bool
    {
        $remoteIp = $this->normalizeIp($serverRequest->getServerParams()['REMOTE_ADDR'] ?? null);

        return null !== $remoteIp && $this->isTrustedProxy($remoteIp);
    }

    public function normalizeIp(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $ip = trim($value);

        return false !== filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    public function isTrustedProxy(string $ip): bool
    {
        $trustedProxies = $this->trustedProxies();
        if ([] === $trustedProxies) {
            return false;
        }

        if (in_array('*', $trustedProxies, true) || in_array($ip, $trustedProxies, true)) {
            return true;
        }

        return array_any($trustedProxies, fn ($trustedProxy) => str_contains($trustedProxy, '/') && $this->matchesCidr($ip, $trustedProxy));
    }

    /** @return list<string> */
    private function trustedProxies(): array
    {
        $value = $this->clientIpConfig['trusted_proxies'] ?? [];
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_map(
            static fn (mixed $proxy): string => trim((string) $proxy),
            $value,
        )
                |> (fn ($x) => array_filter($x, static fn (string $proxy): bool => '' !== $proxy))
                |> array_values(...);
    }

    private function matchesCidr(string $ip, string $cidr): bool
    {
        if (1 !== preg_match('/^([^\/]+)\/(\d{1,3})$/', $cidr, $matches)) {
            return false;
        }

        $network = $this->normalizeIp($matches[1]);
        if (null === $network) {
            return false;
        }

        $prefix        = (int) $matches[2];
        $ipBinary      = @inet_pton($ip);
        $networkBinary = @inet_pton($network);
        if (false === $ipBinary || false === $networkBinary || strlen($ipBinary) !== strlen($networkBinary)) {
            return false;
        }

        $bits = strlen($ipBinary) * 8;
        if ($prefix < 0 || $prefix > $bits) {
            return false;
        }

        $fullBytes     = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes)) {
            return false;
        }

        if (0 === $remainingBits) {
            return true;
        }

        $mask = 0xFF << (8 - $remainingBits) & 0xFF;

        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($networkBinary[$fullBytes]) & $mask);
    }
}
