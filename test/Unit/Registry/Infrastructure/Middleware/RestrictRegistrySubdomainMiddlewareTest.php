<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Middleware;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\Response\TextResponse;
use Packio\Registry\Application\Service\RegistryOrganizationResolver;
use Packio\Registry\Infrastructure\Middleware\RestrictRegistrySubdomainMiddleware;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(RestrictRegistrySubdomainMiddleware::class)]
#[AllowMockObjectsWithoutExpectations]
final class RestrictRegistrySubdomainMiddlewareTest extends TestCase
{
    private const string BASE_DOMAIN  = 'packio.us';
    private const string APP_BASE_URL = 'https://packio.us';

    private BrandingConfig $brandingConfig;

    private RestrictRegistrySubdomainMiddleware $restrictRegistrySubdomainMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brandingConfig = new BrandingConfig([
            'registry_base_domain' => self::BASE_DOMAIN,
            'domain'               => self::BASE_DOMAIN,
            'app_url'              => self::APP_BASE_URL,
        ]);

        $registryOrganizationResolver = new RegistryOrganizationResolver($this->brandingConfig);

        $this->restrictRegistrySubdomainMiddleware = new RestrictRegistrySubdomainMiddleware(
            $registryOrganizationResolver,
            $this->brandingConfig,
        );
    }

    public function testComposerPackagesJsonPassesThroughOnRegistrySubdomain(): void
    {
        $this->assertComposerPathAllowed('/packages.json');
    }

    public function testComposerPrivatePackagesJsonPassesThroughOnRegistrySubdomain(): void
    {
        $this->assertComposerPathAllowed('/private/packages.json');
    }

    public function testComposerP2PassesThroughOnRegistrySubdomain(): void
    {
        $this->assertComposerPathAllowed('/p2/vendor/package');
    }

    public function testComposerP2WithDevSuffixPassesThrough(): void
    {
        $this->assertComposerPathAllowed('/p2/vendor/package~dev');
    }

    public function testComposerDistPassesThroughOnRegistrySubdomain(): void
    {
        $this->assertComposerPathAllowed('/dist/vendor/package/1.0.0/2222222222222222222222222222222222222222.zip');
    }

    public function testNonRegistryDomainPassesThrough(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getPath')->willReturn('/some/page');
        $uri->method('getQuery')->willReturn('');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        $response = $this->createMock(ResponseInterface::class);
        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $result = $this->restrictRegistrySubdomainMiddleware->process($request, $handler);
        self::assertSame($response, $result);
    }

    public function testUiPathOnRegistrySubdomainRedirectsToAppBaseUrl(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('acme.packio.us');
        $uri->method('getPath')->willReturn('/dashboard');
        $uri->method('getQuery')->willReturn('tab=overview');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaderLine')->with('X-Inertia')->willReturn('');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        /** @var RedirectResponse $response */
        $response = $this->restrictRegistrySubdomainMiddleware->process($request, $handler);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://packio.us/dashboard?tab=overview', $response->getHeaderLine('Location'));
    }

    public function testInertiaRequestOnRegistrySubdomainReturns409(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('acme.packio.us');
        $uri->method('getPath')->willReturn('/settings');
        $uri->method('getQuery')->willReturn('');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaderLine')->with('X-Inertia')->willReturn('true');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        /** @var RedirectResponse $response */
        $response = $this->restrictRegistrySubdomainMiddleware->process($request, $handler);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('https://packio.us/settings', $response->getHeaderLine('X-Inertia-Location'));
    }

    private function assertComposerPathAllowed(string $path): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('acme.packio.us');
        $uri->method('getPath')->willReturn($path);
        $uri->method('getQuery')->willReturn('');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        $textResponse = new TextResponse('ok', 200, [
            'Set-Cookie' => 'PACKIO_SESSID=test',
        ]);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($textResponse);

        $response = $this->restrictRegistrySubdomainMiddleware->process($request, $handler);
        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
        self::assertNotSame('', $response->getHeaderLine('Cache-Control'));
    }
}
