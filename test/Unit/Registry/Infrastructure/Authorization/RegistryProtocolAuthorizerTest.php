<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Authorization;

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
use Packio\Shared\Api\Dto\OrganizationDto;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function get_object_vars;

#[CoversClass(RegistryProtocolAuthorizationResult::class)]
#[CoversClass(RegistryProtocolAuthorizationStatus::class)]
#[CoversClass(RegistryProtocolAuthorizer::class)]
#[AllowMockObjectsWithoutExpectations]
final class RegistryProtocolAuthorizerTest extends TestCase
{
    private const array RATE_LIMIT_CONFIG = [
        'registry_auth' => [
            'max_attempts'  => 30,
            'decay_seconds' => 60,
        ],
    ];

    private IdentityApiFacadeInterface&MockObject $identityApi;
    private MockObject&TokenPermissionEvaluatorInterface $permissionEvaluator;
    private MockObject&PackageQueryPort $packageQuery;
    private MockObject&OrganizationApiFacadeInterface $organizationApi;
    private MockObject&RateLimiterInterface $rateLimiter;
    private ClientIpResolverInterface&MockObject $clientIpResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->identityApi         = $this->createMock(IdentityApiFacadeInterface::class);
        $this->permissionEvaluator = $this->createMock(TokenPermissionEvaluatorInterface::class);
        $this->packageQuery        = $this->createMock(PackageQueryPort::class);
        $this->organizationApi     = $this->createMock(OrganizationApiFacadeInterface::class);
        $this->rateLimiter         = $this->createMock(RateLimiterInterface::class);
        $this->clientIpResolver    = $this->createMock(ClientIpResolverInterface::class);
    }

    public function testAllowsPublicPackageAnonymouslyWhenAnonymousAccessIsEnabled(): void
    {
        $organizationDto = $this->createOrganization('org-01', 'acme', anonymousAccess: true);
        $packageData     = new PackageData('vendor/pkg', 'vendor/pkg', 'library', 'src-01', 'org-01', true, 'public', null);

        $serverRequest = $this->createRequest('repo.package.metadata', $organizationDto, 'vendor', 'pkg');

        $this->packageQuery
            ->expects(self::once())
            ->method('findPackageInOrganization')
            ->with('org-01', 'vendor/pkg')
            ->willReturn($packageData)
        ;
        $this->identityApi->expects(self::never())->method('authenticateRegistryAccessToken');
        $this->rateLimiter->expects(self::never())->method('attempt');

        $registryProtocolAuthorizationResult = $this->authorizer()->authorize($serverRequest, 'stale-token');

        self::assertSame(RegistryProtocolAuthorizationStatus::AnonymousAllowed, $registryProtocolAuthorizationResult->status);
    }

    public function testRequiresAuthForPrivatePackageEvenWhenAnonymousAccessIsEnabled(): void
    {
        $organizationDto = $this->createOrganization('org-01', 'acme', anonymousAccess: true);
        $packageData     = new PackageData('vendor/private', 'vendor/private', 'library', 'src-01', 'org-01', true, 'private', null);

        $serverRequest = $this->createRequest('repo.package.metadata', $organizationDto, 'vendor', 'private');

        $this->packageQuery
            ->expects(self::once())
            ->method('findPackageInOrganization')
            ->with('org-01', 'vendor/private')
            ->willReturn($packageData)
        ;
        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');
        $this->rateLimiter
            ->expects(self::once())
            ->method('attempt')
            ->with('registry_auth:127.0.0.1', 30, 60)
            ->willReturn(true)
        ;

        $registryProtocolAuthorizationResult = $this->authorizer()->authorize($serverRequest, null);

        self::assertSame(RegistryProtocolAuthorizationStatus::Unauthorized, $registryProtocolAuthorizationResult->status);
    }

    public function testAllowsValidTokenForCurrentOrganization(): void
    {
        $organizationDto        = $this->createOrganization('org-01', 'acme');
        $registryAccessTokenDto = $this->createToken('org-01', ['packages.read.private']);
        $serverRequest          = $this->createRequest('repo.private.packages', $organizationDto);

        $this->identityApi
            ->expects(self::once())
            ->method('authenticateRegistryAccessToken')
            ->with('valid-token')
            ->willReturn($registryAccessTokenDto)
        ;
        $this->permissionEvaluator
            ->expects(self::once())
            ->method('allows')
            ->with('packages.read.private', ['packages.read.private'])
            ->willReturn(true)
        ;
        $this->rateLimiter->expects(self::never())->method('attempt');

        $registryProtocolAuthorizationResult = $this->authorizer()->authorize($serverRequest, 'valid-token');

        self::assertSame(RegistryProtocolAuthorizationStatus::TokenAllowed, $registryProtocolAuthorizationResult->status);
        self::assertSame($registryAccessTokenDto, $registryProtocolAuthorizationResult->registryActor);
        self::assertNull($registryProtocolAuthorizationResult->resolvedOrganization);
    }

    public function testResolvesOrganizationFromTokenWhenRequestHasNoOrganization(): void
    {
        $organizationDto        = $this->createOrganization('org-01', 'acme');
        $registryAccessTokenDto = $this->createToken('org-01', ['packages.read.private']);
        $serverRequest          = $this->createRequest('repo.private.packages');

        $this->identityApi->method('authenticateRegistryAccessToken')->with('valid-token')->willReturn($registryAccessTokenDto);
        $this->permissionEvaluator->method('allows')->with('packages.read.private', ['packages.read.private'])->willReturn(true);
        $this->organizationApi
            ->expects(self::once())
            ->method('findOrganization')
            ->with('org-01')
            ->willReturn($organizationDto)
        ;

        $registryProtocolAuthorizationResult = $this->authorizer()->authorize($serverRequest, 'valid-token');

        self::assertSame(RegistryProtocolAuthorizationStatus::TokenAllowed, $registryProtocolAuthorizationResult->status);
        self::assertSame($registryAccessTokenDto, $registryProtocolAuthorizationResult->registryActor);
        self::assertSame($organizationDto, $registryProtocolAuthorizationResult->resolvedOrganization);
    }

    public function testDeniesTokenWithoutRequiredPermission(): void
    {
        $organizationDto        = $this->createOrganization('org-01', 'acme');
        $registryAccessTokenDto = $this->createToken('org-01', []);
        $serverRequest          = $this->createRequest('repo.private.packages', $organizationDto);

        $this->identityApi->method('authenticateRegistryAccessToken')->with('no-permission')->willReturn($registryAccessTokenDto);
        $this->permissionEvaluator->method('allows')->with('packages.read.private', [])->willReturn(false);
        $this->rateLimiter->expects(self::never())->method('attempt');

        $registryProtocolAuthorizationResult = $this->authorizer()->authorize($serverRequest, 'no-permission');

        self::assertSame(RegistryProtocolAuthorizationStatus::PermissionDenied, $registryProtocolAuthorizationResult->status);
    }

    public function testDeniesBannedTokenOwnerBeforePermissionEvaluation(): void
    {
        $serverRequest = $this->createRequest('repo.private.packages', $this->createOrganization('org-01', 'acme'));
        $token         = $this->createToken('org-01', ['packages.read.private']);
        $token         = new RegistryAccessTokenDto(...[
            ...get_object_vars($token),
            'isOwnerBanned' => true,
        ]);
        $this->identityApi->expects(self::once())->method('authenticateRegistryAccessToken')->with('banned-token')->willReturn($token);
        $this->permissionEvaluator->expects(self::never())->method('allows');
        $this->rateLimiter->expects(self::never())->method('attempt');

        $registryProtocolAuthorizationResult = $this->authorizer()->authorize($serverRequest, 'banned-token');

        self::assertSame(RegistryProtocolAuthorizationStatus::UserBanned, $registryProtocolAuthorizationResult->status);
    }

    public function testDeniesTokenFromDifferentOrganization(): void
    {
        $organizationDto        = $this->createOrganization('org-a', 'org-a');
        $registryAccessTokenDto = $this->createToken('org-b', ['packages.read.private']);
        $serverRequest          = $this->createRequest('repo.private.packages', $organizationDto);

        $this->identityApi->method('authenticateRegistryAccessToken')->with('cross-org')->willReturn($registryAccessTokenDto);
        $this->permissionEvaluator->method('allows')->with('packages.read.private', ['packages.read.private'])->willReturn(true);
        $this->rateLimiter->expects(self::never())->method('attempt');

        $registryProtocolAuthorizationResult = $this->authorizer()->authorize($serverRequest, 'cross-org');

        self::assertSame(RegistryProtocolAuthorizationStatus::OrganizationMismatch, $registryProtocolAuthorizationResult->status);
    }

    public function testReturnsTokenOrganizationNotFoundWhenTokenOrganizationCannotBeResolved(): void
    {
        $registryAccessTokenDto = $this->createToken('missing-org', ['packages.read.private']);
        $serverRequest          = $this->createRequest('repo.private.packages');

        $this->identityApi->method('authenticateRegistryAccessToken')->with('valid-token')->willReturn($registryAccessTokenDto);
        $this->permissionEvaluator->method('allows')->with('packages.read.private', ['packages.read.private'])->willReturn(true);
        $this->organizationApi->method('findOrganization')->with('missing-org')->willReturn(null);

        $registryProtocolAuthorizationResult = $this->authorizer()->authorize($serverRequest, 'valid-token');

        self::assertSame(RegistryProtocolAuthorizationStatus::TokenOrganizationNotFound, $registryProtocolAuthorizationResult->status);
    }

    public function testInvalidTokenFallsThroughToRateLimitedUnauthorized(): void
    {
        $organizationDto = $this->createOrganization('org-01', 'acme');
        $serverRequest   = $this->createRequest('repo.private.packages', $organizationDto);

        $this->identityApi->method('authenticateRegistryAccessToken')->with('bad-token')->willReturn(null);
        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');
        $this->rateLimiter
            ->expects(self::once())
            ->method('attempt')
            ->with('registry_auth:127.0.0.1', 30, 60)
            ->willReturn(true)
        ;

        $registryProtocolAuthorizationResult = $this->authorizer()->authorize($serverRequest, 'bad-token');

        self::assertSame(RegistryProtocolAuthorizationStatus::Unauthorized, $registryProtocolAuthorizationResult->status);
    }

    public function testInvalidTokenReturnsRateLimitedWhenLimitIsExceeded(): void
    {
        $organizationDto = $this->createOrganization('org-01', 'acme');
        $serverRequest   = $this->createRequest('repo.private.packages', $organizationDto);

        $this->identityApi->method('authenticateRegistryAccessToken')->with('bad-token')->willReturn(null);
        $this->clientIpResolver->method('resolve')->willReturn('127.0.0.1');
        $this->rateLimiter
            ->expects(self::once())
            ->method('attempt')
            ->with('registry_auth:127.0.0.1', 30, 60)
            ->willReturn(false)
        ;

        $registryProtocolAuthorizationResult = $this->authorizer()->authorize($serverRequest, 'bad-token');

        self::assertSame(RegistryProtocolAuthorizationStatus::RateLimited, $registryProtocolAuthorizationResult->status);
        self::assertSame(60, $registryProtocolAuthorizationResult->retryAfter);
    }

    public function testInvalidRateLimitConfigReturnsPolicyMissing(): void
    {
        $serverRequest = $this->createRequest('repo.private.packages');

        $registryProtocolAuthorizationResult = $this->authorizer([])->authorize($serverRequest, null);

        self::assertSame(RegistryProtocolAuthorizationStatus::RateLimitPolicyMissing, $registryProtocolAuthorizationResult->status);
        self::assertSame(60, $registryProtocolAuthorizationResult->retryAfter);
    }

    /**
     * @param array<string, mixed> $rateLimitConfig
     */
    private function authorizer(array $rateLimitConfig = self::RATE_LIMIT_CONFIG): RegistryProtocolAuthorizer
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

    private function createRequest(
        string $routeName,
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

        return $request;
    }

    /**
     * @param list<string> $permissions
     */
    private function createToken(string $organizationId, array $permissions): RegistryAccessTokenDto
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
