<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Branding;

use Common\Branding\BrandingConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BrandingConfig::class)]
final class BrandingConfigTest extends TestCase
{
    public function testRegistryBaseUrlUsesAppPortWhenRegistryDomainHasNoPort(): void
    {
        $brandingConfig = new BrandingConfig([
            'registry_base_domain' => 'localhost',
            'app_url'              => 'http://localhost:8080',
        ]);

        self::assertSame('localhost', $brandingConfig->registryBaseDomain());
        self::assertSame('acme.localhost:8080', $brandingConfig->registryHost('acme'));
        self::assertSame('http://acme.localhost:8080', $brandingConfig->registryBaseUrl('acme'));
    }

    public function testRegistryBaseDomainPortOverridesAppPort(): void
    {
        $brandingConfig = new BrandingConfig([
            'registry_base_domain' => 'localhost:9090',
            'app_url'              => 'http://localhost:8080',
        ]);

        self::assertSame('localhost', $brandingConfig->registryBaseDomain());
        self::assertSame('acme.localhost:9090', $brandingConfig->registryHost('acme'));
        self::assertSame('http://acme.localhost:9090', $brandingConfig->registryBaseUrl('acme'));
    }

    public function testRegistryBaseUrlKeepsProductionDomainWithoutPort(): void
    {
        $brandingConfig = new BrandingConfig([
            'registry_base_domain' => 'packio.us',
            'app_url'              => 'https://packio.us',
        ]);

        self::assertSame('packio.us', $brandingConfig->registryBaseDomain());
        self::assertSame('acme.packio.us', $brandingConfig->registryHost('acme'));
        self::assertSame('https://acme.packio.us', $brandingConfig->registryBaseUrl('acme'));
    }
}
