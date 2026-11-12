<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Handler;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Registry\Api\RegistryApiFacadeInterface;
use Packio\Registry\Infrastructure\Handler\RegistryPackageMetadataHandler;
use Packio\Registry\Infrastructure\RegistryMetadataUrlNormalizer;
use Packio\Shared\Api\Dto\OrganizationDto;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

use function json_decode;

#[CoversClass(RegistryPackageMetadataHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class RegistryPackageMetadataHandlerTest extends TestCase
{
    /** @var MockObject&RegistryApiFacadeInterface */
    private RegistryApiFacadeInterface $registryApiFacade;

    private RegistryPackageMetadataHandler $registryPackageMetadataHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registryApiFacade              = $this->createMock(RegistryApiFacadeInterface::class);
        $this->registryPackageMetadataHandler = new RegistryPackageMetadataHandler($this->registryApiFacade, new RegistryMetadataUrlNormalizer(new BrandingConfig([
            'app_url' => 'https://packio.us',
        ])));
    }

    public function testReturnsMetadataForExistingPackage(): void
    {
        $organizationDto = new OrganizationDto(
            id: 'org-01',
            slug: 'acme',
            name: 'Acme Corp',
            anonymousAccess: true,
        );

        $metadata = [
            'packages' => [
                'vendor/package' => [
                    [
                        'version' => '1.0.0',
                    ],
                ],
            ],
        ];

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('acme.packio.us');
        $uri->method('getPort')->willReturn(null);
        $uri->method('getScheme')->willReturn('https');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            [RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto],
            ['vendor', 'vendor'],
            ['package', 'package'],
        ]);
        $request->method('getUri')->willReturn($uri);

        $this->registryApiFacade
            ->expects(self::once())
            ->method('packageMetadata')
            ->with('vendor/package', 'org-01')
            ->willReturn($metadata)
        ;

        /** @var JsonResponse $response */
        $response = $this->registryPackageMetadataHandler->handle($request);

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        self::assertArrayHasKey('packages', $body);
    }

    public function testReturns404WhenPackageNotFound(): void
    {
        $organizationDto = new OrganizationDto(
            id: 'org-01',
            slug: 'acme',
            name: 'Acme Corp',
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            [RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto],
            ['vendor', 'vendor'],
            ['package', 'nonexistent'],
        ]);
        $request->method('getUri')->willReturn($this->createMock(UriInterface::class));

        $this->registryApiFacade
            ->expects(self::once())
            ->method('packageMetadata')
            ->with('vendor/nonexistent', 'org-01')
            ->willReturn(null)
        ;

        /** @var JsonResponse $response */
        $response = $this->registryPackageMetadataHandler->handle($request);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testReturns404WhenOrganizationNotFound(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            [RequestContextAttributes::CURRENT_ORGANIZATION, null],
            ['vendor', 'vendor'],
            ['package', 'package'],
        ]);

        /** @var JsonResponse $response */
        $response = $this->registryPackageMetadataHandler->handle($request);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testReturns422ForInvalidPackageName(): void
    {
        $organizationDto = new OrganizationDto(
            id: 'org-01',
            slug: 'acme',
            name: 'Acme Corp',
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            [RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto],
            ['vendor', 'BAD!'],
            ['package', 'package'],
        ]);

        /** @var JsonResponse $response */
        $response = $this->registryPackageMetadataHandler->handle($request);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testHandlesDevSuffixInPackageSegment(): void
    {
        $organizationDto = new OrganizationDto(
            id: 'org-01',
            slug: 'acme',
            name: 'Acme Corp',
        );

        $metadata = [
            'packages' => [
                'vendor/package' => [
                    [
                        'version' => 'dev-main',
                    ],
                ],
            ],
        ];

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('acme.packio.us');
        $uri->method('getPort')->willReturn(null);
        $uri->method('getScheme')->willReturn('https');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            [RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto],
            ['vendor', 'vendor'],
            ['package', 'package~dev'],
        ]);
        $request->method('getUri')->willReturn($uri);

        $this->registryApiFacade
            ->expects(self::once())
            ->method('packageMetadata')
            ->with('vendor/package', 'org-01')  // ~dev suffix stripped
            ->willReturn($metadata)
        ;

        /** @var JsonResponse $response */
        $response = $this->registryPackageMetadataHandler->handle($request);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testHandles400ForMissingVendorOrPackage(): void
    {
        $organizationDto = new OrganizationDto(
            id: 'org-01',
            slug: 'acme',
            name: 'Acme Corp',
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            [RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto],
            ['vendor', ''],
            ['package', ''],
        ]);

        /** @var JsonResponse $response */
        $response = $this->registryPackageMetadataHandler->handle($request);

        self::assertSame(400, $response->getStatusCode());
    }
}
