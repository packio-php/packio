<?php

declare(strict_types=1);

namespace Packio\Application\Security\Http;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

use function array_reverse;
use function count;
use function explode;
use function implode;
use function in_array;
use function strlen;

final readonly class TrustedProxyClientIpResolver implements ClientIpResolverInterface
{
    public function __construct(
        private TrustedProxyMatcher $trustedProxyMatcher = new TrustedProxyMatcher(),
        private string $trustedHeader = 'X-Forwarded-For',
    ) {
        if (! in_array($trustedHeader, ['X-Forwarded-For', 'X-Real-IP'], true)) {
            throw new InvalidArgumentException('Trusted client IP header must be X-Forwarded-For or X-Real-IP.');
        }
    }

    public function resolve(ServerRequestInterface $serverRequest): ?string
    {
        $remoteIp = $this->trustedProxyMatcher->normalizeIp($serverRequest->getServerParams()['REMOTE_ADDR'] ?? null);
        if (null === $remoteIp || ! $this->trustedProxyMatcher->isTrustedRequestProxy($serverRequest)) {
            return $remoteIp;
        }

        if ('X-Real-IP' === $this->trustedHeader) {
            $values = $serverRequest->getHeader('X-Real-IP');

            return 1 === count($values)
                ? $this->trustedProxyMatcher->normalizeIp($values[0]) ?? $remoteIp
                : $remoteIp;
        }

        $headers = $serverRequest->getHeader('X-Forwarded-For');
        $bytes   = 0;
        foreach ($headers as $header) {
            $bytes += strlen($header);
            if ($bytes > 4096) {
                return $remoteIp;
            }
        }

        $candidates = explode(',', implode(',', $headers));
        if (count($candidates) > 32) {
            return $remoteIp;
        }

        $ips = [];
        foreach ($candidates as $candidate) {
            $ip = $this->trustedProxyMatcher->normalizeIp($candidate);
            if (null === $ip) {
                return $remoteIp;
            }

            $ips[] = $ip;
        }

        foreach (array_reverse($ips) as $ip) {
            if (! $this->trustedProxyMatcher->isTrustedProxy($ip)) {
                return $ip;
            }
        }

        return $remoteIp;
    }
}
