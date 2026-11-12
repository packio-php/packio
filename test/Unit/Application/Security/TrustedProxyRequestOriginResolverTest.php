<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Security;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Security\Http\TrustedProxyMatcher;
use Packio\Application\Security\Http\TrustedProxyRequestOriginResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TrustedProxyRequestOriginResolver::class)]
final class TrustedProxyRequestOriginResolverTest extends TestCase
{
    /** @param array{scheme: string, host: string, port: null|int} $expected */
    #[Test]
    #[DataProvider('forwardedHosts')]
    public function itIgnoresForwardedHostsWithUserinfo(string $forwardedHost, array $expected): void
    {
        $serverRequest = new ServerRequest([
            'REMOTE_ADDR' => '10.0.0.1',
        ], [], 'http://internal.test:8080/settings', 'POST')
            ->withHeader('X-Forwarded-Host', $forwardedHost)
        ;

        self::assertSame($expected, new TrustedProxyRequestOriginResolver(new TrustedProxyMatcher([
            'trusted_proxies' => ['10.0.0.1'],
        ]))->resolve($serverRequest));
    }

    /** @return iterable<string, array{string, array{scheme: string, host: string, port: null|int}}> */
    public static function forwardedHosts(): iterable
    {
        $internal = [
            'scheme' => 'http',
            'host'   => 'internal.test',
            'port'   => 8080,
        ];
        $forwarded = [
            'scheme' => 'http',
            'host'   => 'packio.test',
            'port'   => null,
        ];

        yield 'user' => ['user@packio.test', $internal];

        yield 'user and password' => ['user:pass@packio.test', $internal];

        yield 'password only' => [':pass@packio.test', $internal];

        yield 'plain host' => ['packio.test', $forwarded];

        yield 'path, query and fragment stay accepted' => ['packio.test/x?a=1#b', $forwarded];
    }
}
