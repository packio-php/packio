<?php

declare(strict_types=1);

namespace Common\Infrastructure\HttpClient;

use InvalidArgumentException;

use function explode;
use function filter_var;
use function inet_pton;
use function ord;
use function parse_url;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function strtolower;
use function trim;

/** Only installation configuration may grant exceptions, never source settings. */
final readonly class OutboundUrlPolicy
{
    /** @param array<string, list<string>> $endpoints Exact HTTPS origins mapped to administrator-approved IP addresses. */
    public function __construct(private array $endpoints = [], private HostResolver $hostResolver = new HostResolver()) {}

    /** @return array{host: string, port: int, origin: string} */
    public function parse(string $url): array
    {
        if (preg_match('/[\x00-\x20\x7f\\\]/', $url)) {
            throw new InvalidArgumentException('Invalid outbound HTTPS URL.');
        }
        $parts = parse_url($url);
        if (false === $parts || 'https' !== ($parts['scheme'] ?? '') || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Invalid outbound HTTPS URL.');
        }
        $host = strtolower($parts['host'] ?? '');
        $ip   = trim($host, '[]');
        if ('' === $host || (false === filter_var($ip, FILTER_VALIDATE_IP) && (false === filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains($host, '.') || str_ends_with($host, '.')))) {
            throw new InvalidArgumentException('Invalid outbound host.');
        }

        // Reject alternative numeric IPv4 spellings understood by libcurl.
        if (false === filter_var($ip, FILTER_VALIDATE_IP) && preg_match('/^(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*$/i', $host)) {
            throw new InvalidArgumentException('Invalid outbound host.');
        }
        $port   = $parts['port'] ?? 443;
        $origin = 'https://' . $host . (443 === $port ? '' : ':' . $port);
        if (443 !== $port && ! isset($this->endpoints[$origin])) {
            throw new InvalidArgumentException('Outbound port is not allowed.');
        }

        return [
            'host'   => $host,
            'port'   => $port,
            'origin' => $origin,
        ];
    }

    /** @return array{host: string, port: int, origin: string, addresses: list<string>} */
    public function resolve(string $url): array
    {
        $target    = $this->parse($url);
        $approved  = $this->endpoints[$target['origin']] ?? null;
        $ip        = trim($target['host'], '[]');
        if (null !== $approved && false !== filter_var($ip, FILTER_VALIDATE_IP) && [$ip] !== $approved) {
            throw new InvalidArgumentException('An IP origin must use its own address.');
        }
        $addresses = $approved ?? (false !== filter_var($ip, FILTER_VALIDATE_IP) ? [$ip] : $this->hostResolver->resolve($target['host']));
        if ([] === $addresses) {
            throw new InvalidArgumentException('Outbound host cannot be resolved.');
        }

        foreach ($addresses as $address) {
            if (false === filter_var($address, FILTER_VALIDATE_IP) || (null === $approved && ! $this->isPublicAddress($address))) {
                throw new InvalidArgumentException('Outbound destination is not allowed.');
            }
        }

        return [
            ...$target,
            'addresses' => $addresses,
        ];
    }

    private function isPublicAddress(string $address): bool
    {
        if (false === filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) {
            return false;
        }

        if (str_contains($address, ':')) {
            $bytes = inet_pton($address);

            // Exclude translation/tunnel addresses, which may encapsulate private IPv4.
            return false !== $bytes && (ord($bytes[0]) & 0xE0) === 0x20
                && (0x20 !== ord($bytes[0]) || 0x02 !== ord($bytes[1]))
                && ! (0x20 === ord($bytes[0]) && 0x01 === ord($bytes[1]) && ord($bytes[2]) < 2);
        }

        return (int) explode('.', $address)[0] < 224;
    }
}
