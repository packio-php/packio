<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function getenv;
use function putenv;
use function trim;

final class TrustedProxyConfigurationTest extends TestCase
{
    #[Test]
    #[DataProvider('proxyConfigurations')]
    public function itRejectsWildcardOnlyInProduction(string $production, string $proxies, bool $rejected): void
    {
        $originalProduction = getenv('APP_PRODUCTION');
        $originalProxies    = getenv('TRUSTED_PROXIES');
        putenv('APP_PRODUCTION=' . $production);
        putenv('TRUSTED_PROXIES=' . $proxies);

        try {
            if ($rejected) {
                $this->expectException(RuntimeException::class);
            }

            $config = require __DIR__ . '/../../../../config/autoload/packio.global.php';
            self::assertSame(trim($proxies), $config['security']['client_ip']['trusted_proxies']);
        } finally {
            putenv(false === $originalProduction ? 'APP_PRODUCTION' : 'APP_PRODUCTION=' . $originalProduction);
            putenv(false === $originalProxies ? 'TRUSTED_PROXIES' : 'TRUSTED_PROXIES=' . $originalProxies);
        }
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function proxyConfigurations(): iterable
    {
        yield 'production wildcard' => ['true', '*', true];

        yield 'production mixed wildcard' => ['true', '10.0.0.1, *,2001:db8::/32', true];

        yield 'production numeric bool' => ['1', ' * ', true];

        yield 'production explicit IP/CIDR' => ['true', '10.0.0.1,2001:db8::/32', false];

        yield 'production no proxy' => ['true', '', false];

        yield 'development wildcard' => ['false', '*', false];
    }

    #[Test]
    public function itExposesTheExplicitTrustedClientIpHeader(): void
    {
        $original = getenv('TRUSTED_PROXY_CLIENT_IP_HEADER');

        try {
            putenv('TRUSTED_PROXY_CLIENT_IP_HEADER=X-Real-IP');
            $config = require __DIR__ . '/../../../../config/autoload/packio.global.php';
            self::assertSame('X-Real-IP', $config['security']['client_ip']['header']);
            putenv('TRUSTED_PROXY_CLIENT_IP_HEADER');
            $config = require __DIR__ . '/../../../../config/autoload/packio.global.php';
            self::assertSame('X-Forwarded-For', $config['security']['client_ip']['header']);
        } finally {
            putenv(false === $original ? 'TRUSTED_PROXY_CLIENT_IP_HEADER' : 'TRUSTED_PROXY_CLIENT_IP_HEADER=' . $original);
        }
    }
}
