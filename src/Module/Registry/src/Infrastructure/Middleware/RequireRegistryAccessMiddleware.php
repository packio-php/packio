<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Middleware;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Registry\Infrastructure\Authorization\RegistryProtocolAuthorizationResult;
use Packio\Registry\Infrastructure\Authorization\RegistryProtocolAuthorizationStatus;
use Packio\Registry\Infrastructure\Authorization\RegistryProtocolAuthorizer;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function base64_decode;
use function explode;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function trim;

/**
 * Authenticates Composer registry requests using registry tokens.
 *
 * Flow:
 * 1. For package metadata / dist archive routes: check whether the request can
 *    be served anonymously FIRST (org has anonymous access enabled AND the
 *    target package is public). If yes → pass through immediately, skipping all
 *    auth and rate limiting. A bad/stale Authorization header on a public
 *    package is safely ignored.
 * 2. For routes that require auth (`repo.private.packages`, or metadata/dist
 *    where anonymous access is not sufficient): try token authentication
 *    WITHOUT rate limiting. A valid token always passes (subject to org
 *    boundary and permission checks). An invalid/missing token → apply rate
 *    limiting using `security.rate_limits['registry_auth']` → 401 / 429.
 *
 * Organization boundary:
 * - Token's organization MUST match the request's resolved organization → 403.
 * - Middleware only sets `registry_actor`; it never overrides the org context
 *   when one is already resolved from the request.
 */
final readonly class RequireRegistryAccessMiddleware implements MiddlewareInterface
{
    public function __construct(private BrandingConfig $brandingConfig, private RegistryProtocolAuthorizer $registryProtocolAuthorizer) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $registryProtocolAuthorizationResult = $this->registryProtocolAuthorizer->authorize(
            $request,
            $this->extractToken($request),
        );

        return match ($registryProtocolAuthorizationResult->status) {
            RegistryProtocolAuthorizationStatus::AnonymousAllowed          => $handler->handle($request),
            RegistryProtocolAuthorizationStatus::TokenAllowed              => $handler->handle($this->attachTokenContext($request, $registryProtocolAuthorizationResult)),
            RegistryProtocolAuthorizationStatus::UserBanned                => $this->buildJsonResponse(
                'Account has been banned.',
                403,
            ),
            RegistryProtocolAuthorizationStatus::PermissionDenied          => $this->buildJsonResponse(
                'Registry token does not have the required permissions.',
                403,
            ),
            RegistryProtocolAuthorizationStatus::OrganizationMismatch      => $this->buildJsonResponse(
                'Registry token does not belong to this organization.',
                403,
            ),
            RegistryProtocolAuthorizationStatus::TokenOrganizationNotFound => $this->buildJsonResponse(
                'Organization not found for the registry token.',
                403,
            ),
            RegistryProtocolAuthorizationStatus::RateLimitPolicyMissing    => $this->buildJsonResponse(
                'Rate limit policy is not configured correctly.',
                429,
                [
                    'Retry-After' => (string) ($registryProtocolAuthorizationResult->retryAfter ?? 60),
                ],
            ),
            RegistryProtocolAuthorizationStatus::RateLimited               => $this->buildJsonResponse(
                'Too many registry authentication attempts. Please try again later.',
                429,
                [
                    'Retry-After' => (string) ($registryProtocolAuthorizationResult->retryAfter ?? 60),
                ],
            ),
            RegistryProtocolAuthorizationStatus::Unauthorized              => $this->buildUnauthorizedResponse(),
        };
    }

    private function attachTokenContext(
        ServerRequestInterface $serverRequest,
        RegistryProtocolAuthorizationResult $registryProtocolAuthorizationResult,
    ): ServerRequestInterface {
        $organization = $registryProtocolAuthorizationResult->resolvedOrganization;
        if ($organization instanceof OrganizationDto) {
            $serverRequest = $serverRequest
                ->withAttribute('organization', $organization->slug)
                ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION_SLUG, $organization->slug)
                ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, $organization)
            ;
        }

        return $serverRequest->withAttribute(RequestContextAttributes::REGISTRY_ACTOR, $registryProtocolAuthorizationResult->registryActor);
    }

    private function extractToken(ServerRequestInterface $serverRequest): ?string
    {
        $authHeader = $serverRequest->getHeaderLine('Authorization');

        if ('' === $authHeader) {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^Basic\s+(.+)$/i', $authHeader, $matches)) {
            $decoded = base64_decode($matches[1], true);
            if (false === $decoded) {
                return null;
            }
            $decoded = trim($decoded);
            if (str_starts_with($decoded, 'token:')) {
                return trim(explode(':', $decoded, 2)[1] ?? '');
            }

            return $decoded;
        }

        return null;
    }

    /**
     * @param array<non-empty-string, array<string>|string> $headers
     */
    private function buildJsonResponse(string $message, int $status, array $headers = []): ResponseInterface
    {
        $jsonResponse = new JsonResponse(
            [
                'error' => $message,
            ],
            $status,
            $headers,
        );

        if (401 === $status) {
            return $jsonResponse->withHeader(
                'WWW-Authenticate',
                sprintf('Bearer realm="%s"', $this->brandingConfig->registryAuthRealm()),
            );
        }

        return $jsonResponse;
    }

    private function buildUnauthorizedResponse(): ResponseInterface
    {
        return $this->buildJsonResponse(
            'Authentication required. Provide a valid registry token via Authorization header.',
            401,
        );
    }
}
