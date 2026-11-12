<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Infrastructure\HttpClient;

use Common\Infrastructure\HttpClient\SafeGitEnvironment;
use InvalidArgumentException;
use PackioTest\Support\Fake\OutboundPolicyFixture;
use PHPUnit\Framework\TestCase;

final class SafeGitEnvironmentTest extends TestCase
{
    public function testPinsDnsDisablesRedirectsAndInsecureProtocolsAndInheritedConfig(): void
    {
        $environment = OutboundPolicyFixture::git()->forUrl('https://example.test/repo', 'Authorization: Bearer secret');
        $settings    = [];
        for ($index = 0; $index < (int) $environment['GIT_CONFIG_COUNT']; ++$index) {
            $settings[$environment['GIT_CONFIG_KEY_' . $index]] = $environment['GIT_CONFIG_VALUE_' . $index];
        }
        self::assertSame('example.test:443:93.184.216.34', $settings['http.curloptResolve']);
        self::assertSame('false', $settings['http.followRedirects']);
        self::assertSame('never', $settings['protocol.allow']);
        self::assertSame('always', $settings['protocol.https.allow']);
        self::assertSame('true', $settings['http.sslVerify']);
        self::assertSame('Authorization: Bearer secret', $settings['http.extraHeader']);
        self::assertSame('', $settings['http.proxy']);
        self::assertSame('/dev/null', $environment['GIT_CONFIG_GLOBAL']);
        self::assertSame('/dev/null', $environment['GIT_CONFIG_SYSTEM']);
        self::assertSame('', $environment['HTTPS_PROXY']);
        self::assertSame('', $environment['GIT_CONFIG_PARAMETERS']);
    }

    public function testSanitizesInheritedGitEnvironmentWithoutDisablingTls(): void
    {
        self::assertSame([
            'PATH'           => '/usr/bin',
            'GIT_SSL_CAINFO' => '/trusted/ca.pem',
        ], SafeGitEnvironment::sanitize([
            'PATH'                  => '/usr/bin',
            'GIT_SSL_CAINFO'        => '/trusted/ca.pem',
            'GIT_SSL_NO_VERIFY'     => 'false',
            'GIT_CONFIG_PARAMETERS' => 'unsafe',
            'GIT_DIR'               => '/some/repo',
        ]));
        self::assertArrayNotHasKey('GIT_SSL_NO_VERIFY', OutboundPolicyFixture::git()->forUrl('https://example.test'));
    }

    public function testRejectsLocalUrlBeforeStartingGit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SafeGitEnvironment()->forUrl('https://127.0.0.1/repo');
    }
}
