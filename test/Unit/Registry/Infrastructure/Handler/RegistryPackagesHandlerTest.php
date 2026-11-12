<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Handler;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Registry\Api\RegistryApiFacadeInterface;
use Packio\Registry\Infrastructure\Handler\RegistryPackagesHandler;
use Packio\Registry\Infrastructure\RegistryMetadataUrlNormalizer;
use Packio\Shared\Api\Dto\OrganizationDto;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

use function json_decode;

#[CoversClass(RegistryPackagesHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class RegistryPackagesHandlerTest extends TestCase
{
    /** @var MockObject&RegistryApiFacadeInterface */
    private RegistryApiFacadeInterface $registryApiFacade;

    private RegistryPackagesHandler $registryPackagesHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registryApiFacade       = $this->createMock(RegistryApiFacadeInterface::class);
        $this->registryPackagesHandler = new RegistryPackagesHandler($this->registryApiFacade, new RegistryMetadataUrlNormalizer(new BrandingConfig([
            'app_url' => 'https://packio.us',
        ])));
    }

    public function testReturnsPublicBootstrapPayload(): void
    {
        $organizationDto = new OrganizationDto(
            id: 'org-01',
            slug: 'acme',
            name: 'Acme Corp',
            anonymousAccess: true,
        );

        $payload = [
            'metadata-url'       => '/p2/%package%',
            'available-packages' => ['vendor/package'],
        ];

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('acme.packio.localhost');
        $uri->method('getPort')->willReturn(null);
        $uri->method('getScheme')->willReturn('https');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with(RequestContextAttributes::CURRENT_ORGANIZATION)->willReturn($organizationDto);
        $request->method('getUri')->willReturn($uri);

        $this->registryApiFacade
            ->expects(self::once())
            ->method('publicRegistryPayload')
            ->with('org-01')
            ->willReturn($payload)
        ;

        /** @var JsonResponse $response */
        $response = $this->registryPackagesHandler->handle($request);

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        self::assertSame('https://acme.packio.localhost/p2/%package%', $body['metadata-url']);
        self::assertSame(['vendor/package'], $body['available-packages']);
    }

    public function testReturns404WhenOrganizationNotFound(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with(RequestContextAttributes::CURRENT_ORGANIZATION)->willReturn(null);

        /** @var JsonResponse $response */
        $response = $this->registryPackagesHandler->handle($request);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testReturns401WhenAnonymousAccessDisabled(): void
    {
        $organizationDto = new OrganizationDto(
            id: 'org-01',
            slug: 'acme',
            name: 'Acme Corp',
            anonymousAccess: false,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with(RequestContextAttributes::CURRENT_ORGANIZATION)->willReturn($organizationDto);

        /** @var JsonResponse $response */
        $response = $this->registryPackagesHandler->handle($request);

        self::assertSame(401, $response->getStatusCode());
    }
}
