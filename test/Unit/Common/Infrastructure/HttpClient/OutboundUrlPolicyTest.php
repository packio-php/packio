<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Infrastructure\HttpClient;

use Common\Infrastructure\HttpClient\HostResolver;
use Common\Infrastructure\HttpClient\OutboundUrlPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutboundUrlPolicyTest extends TestCase
{
    #[DataProvider('blockedUrls')]
    public function testRejectsUnsafeDestinations(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OutboundUrlPolicy()->resolve($url);
    }

    /** @return iterable<string, array{string}> */
    public static function blockedUrls(): iterable
    {
        foreach (['http://example.com', 'https://user:secret@example.com', 'https://example.com/#fragment', 'https://example.com:8443', 'https://127.0.0.1', 'https://10.0.0.1', 'https://172.16.0.1', 'https://192.168.0.1', 'https://169.254.169.254', 'https://100.64.0.1', 'https://0.0.0.0', 'https://224.0.0.1', 'https://192.0.2.1', 'https://[::1]', 'https://[fc00::1]', 'https://[fe80::1]', 'https://[::ffff:127.0.0.1]', 'https://[2002:7f00:1::1]', 'https://[2001:0:4136:e378::1]', 'https://[64:ff9b::7f00:1]', 'https://2130706433', 'https://0177.0.0.1', 'https://0x7f.0.0.1', 'https://localhost', 'https://example.com\@127.0.0.1', "https://example.com/\r\nx"] as $url) {
            yield $url => [$url];
        }
    }

    public function testRejectsMixedPublicAndPrivateDnsAnswers(): void
    {
        $resolver = $this->createMock(HostResolver::class);
        $resolver->expects(self::once())->method('resolve')->willReturn(['93.184.216.34', '10.0.0.1']);
        $this->expectException(InvalidArgumentException::class);
        new OutboundUrlPolicy(hostResolver: $resolver)->resolve('https://example.com');
    }

    public function testExactAdministratorExceptionPinsAddressesWithoutDns(): void
    {
        $resolver = $this->createMock(HostResolver::class);
        $resolver->expects(self::never())->method('resolve');
        $outboundUrlPolicy = new OutboundUrlPolicy([
            'https://git.company.test:8443' => ['10.0.0.9'],
        ], $resolver);
        self::assertSame(['10.0.0.9'], $outboundUrlPolicy->resolve('https://git.company.test:8443/repo')['addresses']);
        $this->expectException(InvalidArgumentException::class);
        $outboundUrlPolicy->resolve('https://other.company.test:8443/repo');
    }

    public function testPublicIpv4AndIpv6AreAllowed(): void
    {
        $outboundUrlPolicy = new OutboundUrlPolicy();
        self::assertSame(['93.184.216.34'], $outboundUrlPolicy->resolve('https://93.184.216.34')['addresses']);
        self::assertSame(['2606:4700:4700::1111'], $outboundUrlPolicy->resolve('https://[2606:4700:4700::1111]')['addresses']);
    }

    public function testUnresolvableHostFailsClosed(): void
    {
        $resolver = $this->createMock(HostResolver::class);
        $resolver->expects(self::once())->method('resolve')->willReturn([]);
        $this->expectException(InvalidArgumentException::class);
        new OutboundUrlPolicy(hostResolver: $resolver)->resolve('https://example.test');
    }
}
