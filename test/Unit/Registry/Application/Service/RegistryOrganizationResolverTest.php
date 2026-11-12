<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Application\Service;

use Common\Branding\BrandingConfig;
use Packio\Registry\Application\Service\RegistryOrganizationResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(RegistryOrganizationResolver::class)]
#[AllowMockObjectsWithoutExpectations]
final class RegistryOrganizationResolverTest extends TestCase
{
    private const string BASE_DOMAIN = 'packio.us';

    private BrandingConfig $brandingConfig;

    private RegistryOrganizationResolver $registryOrganizationResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brandingConfig = new BrandingConfig([
            'registry_base_domain' => self::BASE_DOMAIN,
            'domain'               => self::BASE_DOMAIN,
            'app_url'              => 'https://app.packio.us',
        ]);

        $this->registryOrganizationResolver = new RegistryOrganizationResolver($this->brandingConfig);
    }

    public function testSlugFromHostExtractsSubdomain(): void
    {
        $slug = $this->registryOrganizationResolver->slugFromHost('acme.packio.us');
        self::assertSame('acme', $slug);
    }

    public function testSlugFromHostReturnsNullForApexDomain(): void
    {
        $slug = $this->registryOrganizationResolver->slugFromHost('packio.us');
        self::assertNull($slug);
    }

    public function testSlugFromHostReturnsNullForWwwSubdomain(): void
    {
        $slug = $this->registryOrganizationResolver->slugFromHost('www.packio.us');
        self::assertNull($slug);
    }

    public function testSlugFromHostReturnsNullForNestedSubdomain(): void
    {
        $slug = $this->registryOrganizationResolver->slugFromHost('team.dev.packio.us');
        self::assertNull($slug);
    }

    public function testSlugFromHostReturnsNullForUnrelatedDomain(): void
    {
        $slug = $this->registryOrganizationResolver->slugFromHost('example.com');
        self::assertNull($slug);
    }

    public function testSlugFromHostHandlesTrailingDot(): void
    {
        $slug = $this->registryOrganizationResolver->slugFromHost('acme.packio.us.');
        self::assertSame('acme', $slug);
    }

    public function testSlugFromHostIsCaseInsensitive(): void
    {
        $slug = $this->registryOrganizationResolver->slugFromHost('ACME.PACKIO.US');
        self::assertSame('acme', $slug);
    }

    public function testSlugFromHostIgnoresConfiguredRegistryPort(): void
    {
        $brandingConfig = new BrandingConfig([
            'registry_base_domain' => 'localhost:8080',
            'domain'               => 'localhost:8080',
            'app_url'              => 'http://localhost:8080',
        ]);
        $registryOrganizationResolver = new RegistryOrganizationResolver($brandingConfig);

        self::assertSame('acme', $registryOrganizationResolver->slugFromHost('acme.localhost'));
    }

    public function testSlugFromRequestDelegatesToSlugFromHost(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('acme.packio.us');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        $slug = $this->registryOrganizationResolver->slugFromRequest($request);
        self::assertSame('acme', $slug);
    }
}
