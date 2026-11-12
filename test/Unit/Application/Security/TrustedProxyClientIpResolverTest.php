<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Security;

use InvalidArgumentException;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Security\Http\TrustedProxyClientIpResolver;
use Packio\Application\Security\Http\TrustedProxyMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_fill;
use function implode;
use function str_repeat;

#[CoversClass(TrustedProxyClientIpResolver::class)]
final class TrustedProxyClientIpResolverTest extends TestCase
{
    #[Test]
    public function itUsesRemoteAddressWhenNotTrusted(): void
    {
        $trustedProxyClientIpResolver = $this->resolver([
            'trusted_proxies' => ['203.0.113.10'],
        ]);

        $serverRequest = new ServerRequest([
            'REMOTE_ADDR' => '198.51.100.9',
        ], [], '/', 'GET')
            ->withHeader('X-Forwarded-For', '203.0.113.15')
        ;

        self::assertSame('198.51.100.9', $trustedProxyClientIpResolver->resolve($serverRequest));
    }

    #[Test]
    public function itFallsBackToRemoteAddressWhenNoForwardedHeadersForTrustedProxy(): void
    {
        $trustedProxyClientIpResolver = $this->resolver([
            'trusted_proxies' => '198.51.100.9',
        ]);
        $serverRequest = new ServerRequest(
            [
                'REMOTE_ADDR' => '198.51.100.9',
            ],
        )->withHeader('X-Forwarded-For', '')
            ->withHeader('CF-Connecting-IP', '')
            ->withHeader('X-Real-IP', '')
        ;

        self::assertSame('198.51.100.9', $trustedProxyClientIpResolver->resolve($serverRequest));
    }

    #[Test]
    public function itIgnoresDedicatedIpHeadersForTrustedProxy(): void
    {
        $trustedProxyClientIpResolver = $this->resolver([
            'trusted_proxies' => ['198.51.100.9'],
        ]);
        $serverRequest = new ServerRequest([
            'REMOTE_ADDR' => '198.51.100.9',
        ], [], '/', 'GET')
            ->withHeader('CF-Connecting-IP', '203.0.113.11')
            ->withHeader('X-Real-IP', '203.0.113.12')
            ->withHeader('X-Forwarded-For', '203.0.113.13, 203.0.113.14')
        ;

        self::assertSame('203.0.113.14', $trustedProxyClientIpResolver->resolve($serverRequest));
    }

    #[Test]
    public function itRejectsPartiallyInvalidForwardedChain(): void
    {
        $trustedProxyClientIpResolver = $this->resolver([
            'trusted_proxies' => ['10.0.0.1'],
        ]);
        $serverRequest = new ServerRequest([
            'REMOTE_ADDR' => '10.0.0.1',
        ], [], '/', 'GET')
            ->withHeader('X-Real-IP', '')
            ->withHeader('X-Forwarded-For', '203.0.113.20, bad-ip')
        ;

        self::assertSame('10.0.0.1', $trustedProxyClientIpResolver->resolve($serverRequest));
    }

    #[Test]
    public function itSupportsTrustedProxyCidr(): void
    {
        $trustedProxyClientIpResolver = $this->resolver([
            'trusted_proxies' => '198.51.100.0/24',
        ]);
        $serverRequest = new ServerRequest([
            'REMOTE_ADDR' => '198.51.100.42',
        ], [], '/', 'GET')
            ->withHeader('X-Forwarded-For', '203.0.113.99')
        ;

        self::assertSame('203.0.113.99', $trustedProxyClientIpResolver->resolve($serverRequest));
    }

    #[Test]
    public function itFallsBackToPeerWhenWildcardTrustsEveryHop(): void
    {
        $trustedProxyClientIpResolver = $this->resolver([
            'trusted_proxies' => ['*'],
        ]);
        $serverRequest = new ServerRequest([
            'REMOTE_ADDR' => '198.51.100.9',
        ], [], '/', 'GET')
            ->withHeader('X-Forwarded-For', '203.0.113.77')
        ;

        self::assertSame('198.51.100.9', $trustedProxyClientIpResolver->resolve($serverRequest));
    }

    #[Test]
    public function itFallsBackToRemoteAddressWhenRemoteIpIsInvalid(): void
    {
        $trustedProxyClientIpResolver = $this->resolver([]);
        $serverRequest                = new ServerRequest([
            'REMOTE_ADDR' => 'not-an-ip',
        ], [], '/', 'GET');

        self::assertNull($trustedProxyClientIpResolver->resolve($serverRequest));
    }

    #[Test]
    public function itFallsBackToRemoteWhenAnyForwardedHeaderIsInvalid(): void
    {
        $trustedProxyClientIpResolver = $this->resolver([
            'trusted_proxies' => ['198.51.100.9'],
        ]);
        $serverRequest = new ServerRequest([
            'REMOTE_ADDR' => '198.51.100.9',
        ], [], '/', 'GET')
            ->withHeader('CF-Connecting-IP', 'bad-ip')
            ->withHeader('X-Real-IP', 'not-an-ip')
            ->withHeader('X-Forwarded-For', 'nope')
        ;

        self::assertSame('198.51.100.9', $trustedProxyClientIpResolver->resolve($serverRequest));
    }

    #[Test]
    public function itRejectsSpoofedLeftPrefix(): void
    {
        $serverRequest = new ServerRequest([
            'REMOTE_ADDR' => '10.0.0.1',
        ])
            ->withHeader('X-Forwarded-For', '203.0.113.66, 198.51.100.25')
        ;

        self::assertSame('198.51.100.25', $this->resolver([
            'trusted_proxies' => '10.0.0.1',
        ])->resolve($serverRequest));
    }

    #[Test]
    public function itIgnoresCfHeaderWithoutDedicatedMode(): void
    {
        $serverRequest = new ServerRequest([
            'REMOTE_ADDR' => '10.0.0.1',
        ])
            ->withHeader('X-Forwarded-For', '203.0.113.66, 198.51.100.25')
            ->withHeader('CF-Connecting-IP', '192.0.2.1')
        ;

        self::assertSame('198.51.100.25', $this->resolver([
            'trusted_proxies' => '10.0.0.1',
        ])->resolve($serverRequest));
    }

    /** @param list<string> $headers */
    #[Test]
    #[DataProvider('forwardedChains')]
    public function itResolvesOnlyFullyValidTrustedChains(?string $peer, string $trusted, array $headers, ?string $expected): void
    {
        $serverRequest = new ServerRequest(null === $peer ? [] : [
            'REMOTE_ADDR' => $peer,
        ])
            ->withHeader('X-Forwarded-For', [] === $headers ? '' : $headers)
            ->withHeader('CF-Connecting-IP', '192.0.2.1')
            ->withHeader('X-Real-IP', '192.0.2.2')
        ;

        self::assertSame($expected, $this->resolver([
            'trusted_proxies' => $trusted,
        ])->resolve($serverRequest));
    }

    /** @return iterable<string, array{?string, string, list<string>, ?string}> */
    public static function forwardedChains(): iterable
    {
        yield 'untrusted peer' => [' 198.51.100.25 ', '10.0.0.1', ['203.0.113.66'], '198.51.100.25'];

        yield 'missing peer' => [null, '10.0.0.1', ['198.51.100.25'], null];

        yield 'invalid peer' => ['bad-ip', '*', ['198.51.100.25'], null];

        yield 'repeated headers preserve order' => ['10.0.0.1', '10.0.0.0/8', ['203.0.113.66', '198.51.100.25, 10.0.0.2'], '198.51.100.25'];

        yield 'repeated invalid prefix' => ['10.0.0.1', '10.0.0.0/8', ['bad-ip', '198.51.100.25, 10.0.0.2'], '10.0.0.1'];

        yield 'IPv6 CIDR' => ['2001:db8:abcd::1', '2001:db8:abcd::/48', ['2001:db8:1::66, 2001:db8:2::25, 2001:db8:abcd::2'], '2001:db8:2::25'];

        yield 'IPv6 exact trust' => ['2001:db8::1', '2001:db8::1,2001:db8::2', ['198.51.100.25, 2001:db8::2'], '198.51.100.25'];

        yield 'empty chain' => ['10.0.0.1', '10.0.0.1', [], '10.0.0.1'];

        yield 'empty hop' => ['10.0.0.1', '10.0.0.1', ['198.51.100.25,,203.0.113.66'], '10.0.0.1'];

        yield 'empty repeated value' => ['10.0.0.1', '10.0.0.1', ['', '198.51.100.25'], '10.0.0.1'];

        yield 'invalid left prefix' => ['10.0.0.1', '10.0.0.1', ['bad-ip,198.51.100.25'], '10.0.0.1'];

        yield 'port is malformed' => ['10.0.0.1', '10.0.0.1', ['198.51.100.25:443'], '10.0.0.1'];

        yield 'all trusted' => ['10.0.0.1', '10.0.0.0/8', ['10.0.0.3,10.0.0.2'], '10.0.0.1'];

        yield '32 hops accepted' => ['10.0.0.1', '10.0.0.0/8', [implode(',', ['198.51.100.25', ...array_fill(0, 31, '10.0.0.2')])], '198.51.100.25'];

        yield '33 hops rejected' => ['10.0.0.1', '10.0.0.0/8', [implode(',', ['198.51.100.25', ...array_fill(0, 32, '10.0.0.2')])], '10.0.0.1'];

        yield '4096 bytes accepted' => ['10.0.0.1', '10.0.0.0/8', ['198.51.100.25,' . str_repeat(' ', 4074) . '10.0.0.2'], '198.51.100.25'];

        yield '4097 bytes rejected' => ['10.0.0.1', '10.0.0.0/8', ['198.51.100.25,' . str_repeat(' ', 4075) . '10.0.0.2'], '10.0.0.1'];

        yield 'repeated values exceed total bytes' => ['10.0.0.1', '10.0.0.0/8', ['203.0.113.66,' . str_repeat(' ', 2030) . '10.0.0.2', '198.51.100.25,' . str_repeat(' ', 2030) . '10.0.0.2'], '10.0.0.1'];
    }

    /** @param list<string> $values */
    #[Test]
    #[DataProvider('realIpHeaders')]
    public function itUsesOnlySingleValidRealIpFromTrustedPeer(?string $peer, array $values, ?string $expected): void
    {
        $trustedProxyClientIpResolver = new TrustedProxyClientIpResolver(
            new TrustedProxyMatcher([
                'trusted_proxies' => '10.0.0.20',
            ]),
            'X-Real-IP',
        );
        $request = new ServerRequest(null === $peer ? [] : [
            'REMOTE_ADDR' => $peer,
        ])
            ->withHeader('X-Forwarded-For', '192.0.2.99, 203.0.113.8')
            ->withHeader('CF-Connecting-IP', '192.0.2.88')
        ;

        if ([] !== $values) {
            $request = $request->withHeader('X-Real-IP', $values);
        }

        self::assertSame($expected, $trustedProxyClientIpResolver->resolve($request));
    }

    /** @return iterable<string, array{?string, list<string>, ?string}> */
    public static function realIpHeaders(): iterable
    {
        yield 'trusted peer' => ['10.0.0.20', ['198.51.100.25'], '198.51.100.25'];

        yield 'untrusted peer' => ['203.0.113.8', ['198.51.100.25'], '203.0.113.8'];

        yield 'missing peer' => [null, ['198.51.100.25'], null];

        yield 'invalid peer' => ['bad', ['198.51.100.25'], null];

        yield 'missing header' => ['10.0.0.20', [], '10.0.0.20'];

        yield 'invalid header' => ['10.0.0.20', ['bad'], '10.0.0.20'];

        yield 'comma separated' => ['10.0.0.20', ['198.51.100.25,203.0.113.8'], '10.0.0.20'];

        yield 'repeated header' => ['10.0.0.20', ['198.51.100.25', '203.0.113.8'], '10.0.0.20'];

        yield 'identical repeats' => ['10.0.0.20', ['198.51.100.25', '198.51.100.25'], '10.0.0.20'];

        yield 'IPv6 client' => ['10.0.0.20', ['2001:db8::25'], '2001:db8::25'];
    }

    #[Test]
    public function itRejectsUnsupportedTrustedHeader(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TrustedProxyClientIpResolver(new TrustedProxyMatcher(), 'CF-Connecting-IP');
    }

    /** @param array<string, mixed> $clientIpConfig */
    private function resolver(array $clientIpConfig): TrustedProxyClientIpResolver
    {
        return new TrustedProxyClientIpResolver(new TrustedProxyMatcher($clientIpConfig));
    }
}
