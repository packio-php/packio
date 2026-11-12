<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Middleware;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\RateLimiter\RateLimiterInterface;
use Packio\Identity\Api\Dto\RegistryAccessTokenDto;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Registry\Application\Dto\PackageData;
use Packio\Registry\Application\Port\PackageQueryPort;
use Packio\Registry\Application\Service\TokenPermissionEvaluatorInterface;
use Packio\Registry\Infrastructure\Authorization\RegistryProtocolAuthorizationResult;
use Packio\Registry\Infrastructure\Authorization\RegistryProtocolAuthorizationStatus;
use Packio\Registry\Infrastructure\Authorization\RegistryProtocolAuthorizer;
use Packio\Registry\Infrastructure\Middleware\RequireRegistryAccessMiddleware;
use Packio\Shared\Api\Dto\OrganizationDto;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function base64_encode;
use function json_decode;

#[CoversClass(RegistryProtocolAuthorizationResult::class)]
#[CoversClass(RegistryProtocolAuthorizationStatus::class)]
#[CoversClass(RegistryProtocolAuthorizer::class)]
#[CoversClass(RequireRegistryAccessMiddleware::class)]
#[AllowMockObjectsWithoutExpectations]
final class RequireRegistryAccessMiddlewareTest extends TestCase
{
    private const array RATE_LIMIT_CONFIG = [
        'registry_auth' => [
            'max_attempts'  => 30,
            'decay_seconds' => 60,
        ],
    ];

    private BrandingConfig $brandingConfig;
    private IdentityApiFacadeInterface&MockObject $identityApi;
    private MockObject&TokenPermissionEvaluatorInterface $permissionEvaluator;
    private MockObject&PackageQueryPort $packageQuery;
    private MockObject&OrganizationApiFacadeInterface $organizationApi;
    private MockObject&RateLimiterInterface $rateLimiter;
    private ClientIpResolverInterface&MockObject $clientIpResolver;
    private RequireRegistryAccessMiddleware $requireRegistryAccessMiddleware;

    /** @var array<string, array{max_attempts: int, decay_seconds: int}> */
    private array $rateLimitConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brandingConfig = new BrandingConfig([
            'registry_auth_realm'  => 'Packio',
            'registry_base_domain' => 'packio.us',
            'domain'               => 'packio.us',
            'app_url'              => 'https://packio.us',
        ]);

        $this->identityApi         = $this->createMock(IdentityApiFacadeInterface::class);
        $this->permissionEvaluator = $this->createMock(TokenPermissionEvaluatorInterface::class);
        $this->packageQuery        = $this->createMock(PackageQueryPort::class);
        $this->organizationApi     = $this->createMock(OrganizationApiFacadeInterface::class);
        $this->rateLimiter         = $this->createMock(RateLimiterInterface::class);
        $this->clientIpResolver    = $this->createMock(ClientIpResolverInterface::class);
        $this->rateLimitConfig     = self::RATE_LIMIT_CONFIG;

        $this->requireRegistryAccessMiddleware = new RequireRegistryAccessMiddleware(
            $this->brandingConfig,
            $this->createAuthorizer($this->rateLimitConfig),
        );
    }

    // ── Private bootstrap: no token ─────────────────────────────────────

    public function testPrivateBootstrapWithoutTokenReturns401(): void
    {
        $serverRequest = $this->createRequest('repo.private.packages', 'GET', []);
        $handler       = $this->createHandler();

        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');
        // Rate limit not exceeded → proceed to route check → 401
        $this->rateLimiter
            ->expects(self::once())
            ->method('attempt')
            ->with('registry_auth:127.0.0.1', 30, 60)
            ->willReturn(true)
        ;

        /** @var JsonResponse $response */
        $response = $this->requireRegistryAccessMiddleware->process($serverRequest, $handler);

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('Bearer', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function testPrivateBootstrapWithoutTokenReturns429WhenRateLimited(): void
    {
        $serverRequest = $this->createRequest('repo.private.packages', 'GET', []);
        $handler       = $this->createHandler();

        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');
        // Rate limit exceeded
        $this->rateLimiter
            ->expects(self::once())
            ->method('attempt')
            ->with('registry_auth:127.0.0.1', 30, 60)
            ->willReturn(false)
        ;

        /** @var JsonResponse $response */
        $response = $this->requireRegistryAccessMiddleware->process($serverRequest, $handler);

        self::assertSame(429, $response->getStatusCode());
    }

    // ── Private bootstrap: with token ──────────────────────────────────

    public function testPrivateBootstrapWithValidBearerTokenSkipsRateLimit(): void
    {
        $organizationDto        = $this->createOrganization('org-01', 'acme');
        $registryAccessTokenDto = $this->createTokenDto('org-01', ['packages.read.private']);

        $serverRequest = $this->createRequest('repo.private.packages', 'GET', [
            'Authorization' => 'Bearer valid-token',
        ], $organizationDto);
        $handler = $this->createHandler();

        // Token auth happens before rate limiting → rateLimiter should NOT be called for a valid token
        $this->identityApi->method('authenticateRegistryAccessToken')->with('valid-token')->willReturn($registryAccessTokenDto);
        $this->permissionEvaluator->method('allows')->with('packages.read.private', ['packages.read.private'])->willReturn(true);
        $this->rateLimiter->expects(self::never())->method('attempt');

        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($response);

        $result = $this->requireRegistryAccessMiddleware->process($serverRequest, $handler);
        self::assertSame($response, $result);
    }

    public function testPrivateBootstrapWithValidBasicTokenSkipsRateLimit(): void
    {
        $organizationDto        = $this->createOrganization('org-01', 'acme');
        $registryAccessTokenDto = $this->createTokenDto('org-01', ['packages.read.private']);

        $encoded = base64_encode('token:valid-token');

        $serverRequest = $this->createRequest('repo.private.packages', 'GET', [
            'Authorization' => 'Basic ' . $encoded,
        ], $organizationDto);
        $handler = $this->createHandler();

        $this->identityApi->method('authenticateRegistryAccessToken')->with('valid-token')->willReturn($registryAccessTokenDto);
        $this->permissionEvaluator->method('allows')->with('packages.read.private', ['packages.read.private'])->willReturn(true);
        $this->rateLimiter->expects(self::never())->method('attempt');

        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($response);

        $result = $this->requireRegistryAccessMiddleware->process($serverRequest, $handler);
        self::assertSame($response, $result);
    }

    // ── Invalid token ──────────────────────────────────────────────────

    public function testInvalidTokenHitsRateLimitThenReturns401(): void
    {
        $organizationDto = $this->createOrganization('org-01', 'acme');
        $serverRequest   = $this->createRequest('repo.private.packages', 'GET', [
            'Authorization' => 'Bearer bad-token',
        ], $organizationDto);
        $handler = $this->createHandler();

        // Token auth fails
        $this->identityApi->method('authenticateRegistryAccessToken')->with('bad-token')->willReturn(null);
        // Then rate limit is checked → not exceeded
        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');
        $this->rateLimiter
            ->expects(self::once())
            ->method('attempt')
            ->with('registry_auth:127.0.0.1', 30, 60)
            ->willReturn(true)
        ;

        /** @var JsonResponse $response */
        $response = $this->requireRegistryAccessMiddleware->process($serverRequest, $handler);
        self::assertSame(401, $response->getStatusCode());
    }

    public function testInvalidTokenReturns429WhenRateLimited(): void
    {
        $organizationDto = $this->createOrganization('org-01', 'acme');
        $serverRequest   = $this->createRequest('repo.private.packages', 'GET', [
            'Authorization' => 'Bearer bad-token',
        ], $organizationDto);
        $handler = $this->createHandler();

        // Token auth fails
        $this->identityApi->method('authenticateRegistryAccessToken')->with('bad-token')->willReturn(null);
        // Then rate limit is checked → exceeded
        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');
        $this->rateLimiter
            ->expects(self::once())
            ->method('attempt')
            ->with('registry_auth:127.0.0.1', 30, 60)
            ->willReturn(false)
        ;

        /** @var JsonResponse $response */
        $response = $this->requireRegistryAccessMiddleware->process($serverRequest, $handler);
        self::assertSame(429, $response->getStatusCode());
    }

    // ── Token without permission ───────────────────────────────────────

    public function testValidTokenWithoutPermissionReturns403(): void
    {
        $organizationDto        = $this->createOrganization('org-01', 'acme');
        $registryAccessTokenDto = $this->createTokenDto('org-01', []);

        $serverRequest = $this->createRequest('repo.private.packages', 'GET', [
            'Authorization' => 'Bearer no-perm',
        ], $organizationDto);
        $handler = $this->createHandler();

        $this->identityApi->method('authenticateRegistryAccessToken')->with('no-perm')->willReturn($registryAccessTokenDto);
        $this->permissionEvaluator->method('allows')->with('packages.read.private', [])->willReturn(false);
        $this->rateLimiter->expects(self::never())->method('attempt');

        /** @var JsonResponse $response */
        $response = $this->requireRegistryAccessMiddleware->process($serverRequest, $handler);
        self::assertSame(403, $response->getStatusCode());
    }

    // ── Cross-organization token ───────────────────────────────────────

    public function testTokenFromDifferentOrganizationReturns403(): void
    {
        $organizationDto        = $this->createOrganization('org-a', 'org-a');
        $registryAccessTokenDto = $this->createTokenDto('org-b', ['packages.read.private']);

        $serverRequest = $this->createRequest('repo.private.packages', 'GET', [
            'Authorization' => 'Bearer cross-org',
        ], $organizationDto);
        $handler = $this->createHandler();

        $this->identityApi->method('authenticateRegistryAccessToken')->with('cross-org')->willReturn($registryAccessTokenDto);
        $this->permissionEvaluator->method('allows')->with('packages.read.private', ['packages.read.private'])->willReturn(true);
        $this->rateLimiter->expects(self::never())->method('attempt');

        /** @var JsonResponse $response */
        $response = $this->requireRegistryAccessMiddleware->process($serverRequest, $handler);

        self::assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        self::assertStringContainsString('not belong', $body['error']);
    }

    // ── Anonymous access + package visibility ──────────────────────────

    public function testMetadataAllowsPublicPackageWhenAnonymousEnabled(): void
    {
        $organizationDto = $this->createOrganization('org-01', 'acme', anonymousAccess: true);
        $packageData     = new PackageData('vendor/pkg', 'vendor/pkg', 'library', 'src-01', 'org-01', true, 'public', null);

        $serverRequest = $this->createRequest('repo.package.metadata', 'GET', [], $organizationDto, 'vendor', 'pkg');
        $handler       = $this->createHandler();

        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');
        $this->rateLimiter->expects(self::never())->method('attempt');
        $this->packageQuery->method('findPackageInOrganization')->with('org-01', 'vendor/pkg')->willReturn($packageData);

        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($response);

        self::assertSame($response, $this->requireRegistryAccessMiddleware->process($serverRequest, $handler));
    }

    public function testMetadataBlocksPrivatePackageWhenAnonymousEnabled(): void
    {
        $organizationDto = $this->createOrganization('org-01', 'acme', anonymousAccess: true);
        $packageData     = new PackageData('vendor/private', 'vendor/private', 'library', 'src-01', 'org-01', true, 'private', null);

        $serverRequest = $this->createRequest('repo.package.metadata', 'GET', [], $organizationDto, 'vendor', 'private');
        $handler       = $this->createHandler();

        // tryServeAnonymously returns null (private package), then auth path:
        // no token → rate limit → 401
        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');
        $this->rateLimiter
            ->expects(self::once())
            ->method('attempt')
            ->with('registry_auth:127.0.0.1', 30, 60)
            ->willReturn(true)
        ;
        $this->packageQuery->method('findPackageInOrganization')->with('org-01', 'vendor/private')->willReturn($packageData);

        /** @var JsonResponse $response */
        $response = $this->requireRegistryAccessMiddleware->process($serverRequest, $handler);
        self::assertSame(401, $response->getStatusCode());
    }

    public function testMetadataAllowsMissingPackageWhenAnonymousEnabled(): void
    {
        $organizationDto = $this->createOrganization('org-01', 'acme', anonymousAccess: true);

        $serverRequest = $this->createRequest('repo.package.metadata', 'GET', [], $organizationDto, 'vendor', 'missing');
        $handler       = $this->createHandler();

        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');
        $this->rateLimiter->expects(self::never())->method('attempt');
        $this->packageQuery->method('findPackageInOrganization')->with('org-01', 'vendor/missing')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($response);

        self::assertSame($response, $this->requireRegistryAccessMiddleware->process($serverRequest, $handler));
    }

    public function testMetadataBlocksWhenAnonymousDisabled(): void
    {
        $organizationDto = $this->createOrganization('org-01', 'acme', anonymousAccess: false);

        $serverRequest = $this->createRequest('repo.package.metadata', 'GET', [], $organizationDto);
        $handler       = $this->createHandler();

        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');
        $this->rateLimiter
            ->expects(self::once())
            ->method('attempt')
            ->with('registry_auth:127.0.0.1', 30, 60)
            ->willReturn(true)
        ;

        /** @var JsonResponse $response */
        $response = $this->requireRegistryAccessMiddleware->process($serverRequest, $handler);
        self::assertSame(401, $response->getStatusCode());
    }

    public function testMetadataAllowsPublicPackageWithBadTokenWhenAnonymousEnabled(): void
    {
        // A stale/bad Authorization header on a public package with anonymous
        // access enabled must NOT trigger auth or rate limiting.
        $organizationDto = $this->createOrganization('org-01', 'acme', anonymousAccess: true);
        $packageData     = new PackageData('vendor/pkg', 'vendor/pkg', 'library', 'src-01', 'org-01', true, 'public', null);

        $serverRequest = $this->createRequest(
            'repo.package.metadata',
            'GET',
            [
                'Authorization' => 'Bearer expired-or-stale-token',
            ],
            $organizationDto,
            'vendor',
            'pkg',
        );
        $handler = $this->createHandler();

        // Anonymous check runs FIRST → finds public package → passes through.
        // The bad token is never extracted/authenticated.
        $this->packageQuery->method('findPackageInOrganization')->with('org-01', 'vendor/pkg')->willReturn($packageData);
        $this->identityApi->expects(self::never())->method('authenticateRegistryAccessToken');
        $this->rateLimiter->expects(self::never())->method('attempt');

        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($response);

        self::assertSame($response, $this->requireRegistryAccessMiddleware->process($serverRequest, $handler));
    }

    public function testMetadataAnonymousRequestDoesNotConsumeRateLimit(): void
    {
        // Normal anonymous composer traffic to a public package must NOT
        // consume registry auth rate limit attempts.
        $organizationDto = $this->createOrganization('org-01', 'acme', anonymousAccess: true);
        $packageData     = new PackageData('vendor/pkg', 'vendor/pkg', 'library', 'src-01', 'org-01', true, 'public', null);

        $serverRequest = $this->createRequest('repo.package.metadata', 'GET', [], $organizationDto, 'vendor', 'pkg');
        $handler       = $this->createHandler();

        $this->packageQuery->method('findPackageInOrganization')->with('org-01', 'vendor/pkg')->willReturn($packageData);
        $this->rateLimiter->expects(self::never())->method('attempt');

        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($response);

        self::assertSame($response, $this->requireRegistryAccessMiddleware->process($serverRequest, $handler));
    }

    public function testMetadataBlocksPrivatePackageWithBadTokenWhenAnonymousEnabled(): void
    {
        // Private package with bad token: tryServeAnonymously returns null,
        // then auth path processes the bad token → fails → rate limit → 401.
        $organizationDto = $this->createOrganization('org-01', 'acme', anonymousAccess: true);
        $packageData     = new PackageData('vendor/private', 'vendor/private', 'library', 'src-01', 'org-01', true, 'private', null);

        $serverRequest = $this->createRequest(
            'repo.package.metadata',
            'GET',
            [
                'Authorization' => 'Bearer bad-token',
            ],
            $organizationDto,
            'vendor',
            'private',
        );
        $handler = $this->createHandler();

        $this->packageQuery->method('findPackageInOrganization')->with('org-01', 'vendor/private')->willReturn($packageData);

        // Auth path: bad token → auth fails → rate limit → 401
        $this->identityApi->method('authenticateRegistryAccessToken')->with('bad-token')->willReturn(null);
        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');
        $this->rateLimiter
            ->expects(self::once())
            ->method('attempt')
            ->with('registry_auth:127.0.0.1', 30, 60)
            ->willReturn(true)
        ;

        /** @var JsonResponse $response */
        $response = $this->requireRegistryAccessMiddleware->process($serverRequest, $handler);
        self::assertSame(401, $response->getStatusCode());
    }

    public function testUsesConfiguredRegistryAuthLimitsFromConfig(): void
    {
        $organizationDto = $this->createOrganization('org-01', 'acme');
        $serverRequest   = $this->createRequest('repo.private.packages', 'GET', [
            'Authorization' => 'Bearer bad-token',
        ], $organizationDto);
        $handler = $this->createHandler();

        $this->identityApi
            ->method('authenticateRegistryAccessToken')
            ->with('bad-token')
            ->willReturn(null)
        ;

        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');

        $this->rateLimiter
            ->expects(self::once())
            ->method('attempt')
            ->with('registry_auth:127.0.0.1', 2, 10)
            ->willReturn(false)
        ;

        $requireRegistryAccessMiddleware = $this->createMiddlewareWithRateLimits([
            'registry_auth' => [
                'max_attempts'  => 2,
                'decay_seconds' => 10,
            ],
        ]);

        $response = $requireRegistryAccessMiddleware->process($serverRequest, $handler);
        self::assertSame(429, $response->getStatusCode());
    }

    public function testMetadataAllowsPrivatePackageWithValidTokenWhenAnonymousEnabled(): void
    {
        // Private package with valid token must succeed.
        // tryServeAnonymously returns null → auth path processes valid token → passes.
        $organizationDto        = $this->createOrganization('org-01', 'acme', anonymousAccess: true);
        $packageData            = new PackageData('vendor/private', 'vendor/private', 'library', 'src-01', 'org-01', true, 'private', null);
        $registryAccessTokenDto = $this->createTokenDto('org-01', ['packages.read.private']);

        $serverRequest = $this->createRequest(
            'repo.package.metadata',
            'GET',
            [
                'Authorization' => 'Bearer valid-private-token',
            ],
            $organizationDto,
            'vendor',
            'private',
        );
        $handler = $this->createHandler();

        $this->packageQuery->method('findPackageInOrganization')->with('org-01', 'vendor/private')->willReturn($packageData);
        $this->identityApi->method('authenticateRegistryAccessToken')->with('valid-private-token')->willReturn($registryAccessTokenDto);
        $this->permissionEvaluator->method('allows')->with('packages.read.private', ['packages.read.private'])->willReturn(true);
        // Rate limiter must NOT be called for valid token
        $this->rateLimiter->expects(self::never())->method('attempt');

        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($response);

        self::assertSame($response, $this->requireRegistryAccessMiddleware->process($serverRequest, $handler));
    }

    // ── Dist route ─────────────────────────────────────────────────────

    public function testDistAllowsPublicPackageWhenAnonymousEnabled(): void
    {
        $organizationDto = $this->createOrganization('org-01', 'acme', anonymousAccess: true);
        $packageData     = new PackageData('vendor/pkg', 'vendor/pkg', 'library', 'src-01', 'org-01', true, 'public', null);

        $serverRequest = $this->createRequest('repo.package.dist.reference', 'GET', [], $organizationDto, 'vendor', 'pkg');
        $handler       = $this->createHandler();

        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');
        $this->rateLimiter->expects(self::never())->method('attempt');
        $this->packageQuery->method('findPackageInOrganization')->with('org-01', 'vendor/pkg')->willReturn($packageData);

        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($response);

        self::assertSame($response, $this->requireRegistryAccessMiddleware->process($serverRequest, $handler));
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /**
     * @param array<string, string> $headers
     */
    private function createRequest(
        string $routeName,
        string $method,
        array $headers,
        ?OrganizationDto $organizationDto = null,
        ?string $vendor = null,
        ?string $package = null,
    ): ServerRequestInterface {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $route       = new Route('/test', $middleware, null, $routeName);
        $routeResult = RouteResult::fromRoute($route);

        $attributeMap = [
            [RouteResult::class, $routeResult],
            [RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto],
        ];

        if (null !== $vendor) {
            $attributeMap[] = ['vendor', $vendor];
        }

        if (null !== $package) {
            $attributeMap[] = ['package', $package];
        }

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap($attributeMap);
        $request->method('getMethod')->willReturn($method);
        $request->method('getHeaderLine')->willReturnCallback(fn (string $name): string => $headers[$name] ?? '');

        return $request;
    }

    /**
     * @param array<string, array{max_attempts: int, decay_seconds: int}> $rateLimitConfig
     */
    private function createMiddlewareWithRateLimits(array $rateLimitConfig): RequireRegistryAccessMiddleware
    {
        return new RequireRegistryAccessMiddleware(
            $this->brandingConfig,
            $this->createAuthorizer($rateLimitConfig),
        );
    }

    /**
     * @param array<string, array{max_attempts: int, decay_seconds: int}> $rateLimitConfig
     */
    private function createAuthorizer(array $rateLimitConfig): RegistryProtocolAuthorizer
    {
        return new RegistryProtocolAuthorizer(
            $this->identityApi,
            $this->permissionEvaluator,
            $this->packageQuery,
            $this->organizationApi,
            $this->rateLimiter,
            $this->clientIpResolver,
            $rateLimitConfig,
        );
    }

    private function createHandler(): MockObject&RequestHandlerInterface
    {
        return $this->createMock(RequestHandlerInterface::class);
    }

    /**
     * @param list<string> $permissions
     */
    private function createTokenDto(string $organizationId, array $permissions): RegistryAccessTokenDto
    {
        return new RegistryAccessTokenDto(
            id: 'token-01',
            name: 'Test Token',
            permissions: $permissions,
            createdAt: '2024-01-01T00:00:00+00:00',
            lastUsedAt: '2024-01-01T00:00:00+00:00',
            organizationId: $organizationId,
            maskedToken: 'abc...xyz',
        );
    }

    private function createOrganization(string $id, string $slug, bool $anonymousAccess = true): OrganizationDto
    {
        return new OrganizationDto(
            id: $id,
            slug: $slug,
            name: 'Test Org',
            anonymousAccess: $anonymousAccess,
        );
    }
}
