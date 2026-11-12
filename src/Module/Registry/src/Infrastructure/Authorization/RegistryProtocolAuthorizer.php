<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Authorization;

use Common\Registry\RegistryProtocolPermissions;
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
use Packio\Registry\Application\Service\VendorPackageInput;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;
use function is_array;
use function is_int;
use function is_string;

final readonly class RegistryProtocolAuthorizer
{
    private const string RATE_LIMIT_KEY_PREFIX = 'registry_auth:';
    private const string RATE_LIMIT_KEY        = 'registry_auth';

    private const array ANONYMOUS_CAPABLE_ROUTES = [
        'repo.package.metadata',
        'repo.package.dist.reference',
    ];

    /**
     * @param array<string, mixed> $rateLimitConfig
     */
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private TokenPermissionEvaluatorInterface $tokenPermissionEvaluator,
        private PackageQueryPort $packageQueryPort,
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private RateLimiterInterface $rateLimiter,
        private ClientIpResolverInterface $clientIpResolver,
        private array $rateLimitConfig,
    ) {}

    public function authorize(ServerRequestInterface $serverRequest, ?string $token): RegistryProtocolAuthorizationResult
    {
        if ($this->isAnonymousAllowed($serverRequest)) {
            return RegistryProtocolAuthorizationResult::anonymousAllowed();
        }

        if (null !== $token) {
            $tokenResult = $this->authorizeToken($token, $serverRequest);
            if ($tokenResult instanceof RegistryProtocolAuthorizationResult) {
                return $tokenResult;
            }
        }

        return $this->rateLimitedFailure($serverRequest);
    }

    private function isAnonymousAllowed(ServerRequestInterface $serverRequest): bool
    {
        if (! in_array($this->resolveRouteName($serverRequest), self::ANONYMOUS_CAPABLE_ROUTES, true)) {
            return false;
        }

        $organization = RequestContextAttributes::currentOrganization($serverRequest);

        if (! $organization instanceof OrganizationDto || ! $organization->anonymousAccess) {
            return false;
        }

        $packageName = $this->resolvePackageName($serverRequest);
        if (null === $packageName) {
            return true;
        }

        $package = $this->packageQueryPort->findPackageInOrganization(
            $organization->id,
            $packageName,
        );

        return ! $package instanceof PackageData || 'private' !== $package->visibility;
    }

    private function authorizeToken(string $token, ServerRequestInterface $serverRequest): ?RegistryProtocolAuthorizationResult
    {
        $tokenDto = $this->identityApiFacade->authenticateRegistryAccessToken($token);

        if (! $tokenDto instanceof RegistryAccessTokenDto) {
            return null;
        }

        if ($tokenDto->isOwnerBanned) {
            return RegistryProtocolAuthorizationResult::userBanned();
        }

        if (! $this->tokenPermissionEvaluator->allows(RegistryProtocolPermissions::PACKAGES_READ_PRIVATE, $tokenDto->permissions)) {
            return RegistryProtocolAuthorizationResult::permissionDenied();
        }

        $requestOrg = RequestContextAttributes::currentOrganization($serverRequest);

        if ($requestOrg instanceof OrganizationDto) {
            return $requestOrg->id === $tokenDto->organizationId
                ? RegistryProtocolAuthorizationResult::tokenAllowed($tokenDto)
                : RegistryProtocolAuthorizationResult::organizationMismatch();
        }

        $tokenOrg = $this->organizationApiFacade->findOrganization($tokenDto->organizationId);
        if (! $tokenOrg instanceof OrganizationDto) {
            return RegistryProtocolAuthorizationResult::tokenOrganizationNotFound();
        }

        return RegistryProtocolAuthorizationResult::tokenAllowed($tokenDto, $tokenOrg);
    }

    private function rateLimitedFailure(ServerRequestInterface $serverRequest): RegistryProtocolAuthorizationResult
    {
        $policy = $this->resolveRegistryAuthPolicy();
        if (null === $policy) {
            return RegistryProtocolAuthorizationResult::rateLimitPolicyMissing();
        }

        $clientIp     = $this->clientIpResolver->resolve($serverRequest) ?? 'unknown';
        $rateLimitKey = self::RATE_LIMIT_KEY_PREFIX . $clientIp;

        $allowed = $this->rateLimiter->attempt(
            $rateLimitKey,
            $policy['max_attempts'],
            $policy['decay_seconds'],
        );

        return $allowed
            ? RegistryProtocolAuthorizationResult::unauthorized()
            : RegistryProtocolAuthorizationResult::rateLimited($policy['decay_seconds']);
    }

    /**
     * @return null|array{max_attempts: int, decay_seconds: int}
     */
    private function resolveRegistryAuthPolicy(): ?array
    {
        $policy = $this->rateLimitConfig[self::RATE_LIMIT_KEY] ?? null;
        if (! is_array($policy)) {
            return null;
        }

        $maxAttempts  = $policy['max_attempts'] ?? null;
        $decaySeconds = $policy['decay_seconds'] ?? null;

        if (! is_int($maxAttempts) || ! is_int($decaySeconds) || $maxAttempts < 1 || $decaySeconds < 1) {
            return null;
        }

        return [
            'max_attempts'  => $maxAttempts,
            'decay_seconds' => $decaySeconds,
        ];
    }

    private function resolvePackageName(ServerRequestInterface $serverRequest): ?string
    {
        $vendor  = $serverRequest->getAttribute('vendor');
        $package = $serverRequest->getAttribute('package');

        if (! is_string($vendor) || '' === $vendor || ! is_string($package) || '' === $package) {
            return null;
        }

        $vendorPackageInput = VendorPackageInput::fromSegments($vendor, $package);

        return $vendorPackageInput->isValid() ? $vendorPackageInput->canonical : null;
    }

    private function resolveRouteName(ServerRequestInterface $serverRequest): ?string
    {
        $routeResult = $serverRequest->getAttribute(RouteResult::class);

        if (! $routeResult instanceof RouteResult) {
            return null;
        }

        $name = $routeResult->getMatchedRouteName();

        return is_string($name) ? $name : null;
    }
}
