<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Common\Branding\BrandingConfig;
use Mezzio\Session\SessionInterface;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Infrastructure\Http\ApiPathMatcher;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Release\AppReleaseVersion;
use Packio\Application\Security\Csrf\CsrfTokenManager;
use Packio\Application\Service\InitialListViewModeResolver;
use Packio\Application\Service\InitialThemeResolver;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;

use function in_array;
use function strcmp;
use function usort;

final readonly class HandleInertiaRequests implements MiddlewareInterface
{
    private const string LIST_VIEW_MODE_STORAGE_KEY = 'packio-list-view-mode';

    public function __construct(
        private BrandingConfig $brandingConfig,
        private InitialThemeResolver $initialThemeResolver,
        private InitialListViewModeResolver $initialListViewModeResolver,
        private AppReleaseVersion $appReleaseVersion,
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private ValidationRedirectState $validationRedirectState,
        private CsrfTokenManager $csrfTokenManager,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (ApiPathMatcher::isApiRequest($request)) {
            return $handler->handle($request);
        }

        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        if ($inertia instanceof InertiaInterface) {
            $context              = RequestContextAttributes::context($request);
            $currentUser          = $context->currentUser;
            $userId               = $context->currentUserId;
            $isBanned             = $currentUser instanceof UserDto && $currentUser->isBanned;
            $resolvedOrganization = $isBanned ? null : $this->organizationPayload($context->currentOrganization);
            $consumedErrors       = $this->validationRedirectState->consumeErrors($request);
            $consumedOld          = $this->validationRedirectState->consumeOld($request);

            $inertia->share('auth', [
                'user'  => $currentUser instanceof UserDto ? [
                    'id'           => $currentUser->id,
                    'email'        => $currentUser->email,
                    'display_name' => $currentUser->displayName,
                    'roles'        => $currentUser->roles,
                    'is_admin'     => in_array('admin', $currentUser->roles, true),
                ] : null,
                'check' => $currentUser instanceof UserDto,
            ]);
            $inertia->share('app', [
                'version' => $this->appReleaseVersion->toArray(),
            ]);
            $inertia->share('branding', $this->brandingConfig->toArray());
            $inertia->share('organization', $resolvedOrganization);
            $inertia->share('organizations', $isBanned ? [] : $this->resolveOrganizations($userId));
            $inertia->share('current_membership', $isBanned ? null : $this->membershipPayload($context->currentMembership));
            $inertia->share('errors', $consumedErrors);
            $inertia->share('old', $consumedOld);
            $inertia->share('csrf', [
                'token' => $this->csrfToken($request),
            ]);
            $inertia->share('initial_theme', $this->initialTheme($request));
            $inertia->share('initial_list_view_mode', $this->initialListViewMode($request));
            $inertia->share('current_url', $this->buildFullUrl($request));
        }

        return $handler->handle($request);
    }

    private function csrfToken(ServerRequestInterface $serverRequest): ?string
    {
        $session = $serverRequest->getAttribute(SessionInterface::class);
        if (! $session instanceof SessionInterface) {
            return null;
        }

        return $this->csrfTokenManager->getOrCreate($session);
    }

    /** @return array<string, null|string> */
    private function initialTheme(ServerRequestInterface $serverRequest): array
    {
        return $this->initialThemeResolver->resolve($serverRequest, $this->brandingConfig->themeStorageKey());
    }

    private function initialListViewMode(ServerRequestInterface $serverRequest): string
    {
        return $this->initialListViewModeResolver->resolve($serverRequest, self::LIST_VIEW_MODE_STORAGE_KEY);
    }

    /** @return null|array<string, mixed> */
    private function organizationPayload(?OrganizationDto $organizationDto): ?array
    {
        if (! $organizationDto instanceof OrganizationDto) {
            return null;
        }

        return [
            'id'     => $organizationDto->id,
            'slug'   => $organizationDto->slug,
            'name'   => $organizationDto->name,
            'status' => $organizationDto->status,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveOrganizations(?string $userId): array
    {
        if (null === $userId || '' === $userId) {
            return [];
        }

        $organizations = [];
        foreach ($this->organizationApiFacade->activeOrganizationsForUser($userId) as $organizationDto) {
            $organizations[] = [
                'id'     => $organizationDto->id,
                'slug'   => $organizationDto->slug,
                'name'   => $organizationDto->name,
                'status' => $organizationDto->status,
            ];
        }

        usort($organizations, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $organizations;
    }

    /** @return null|array<string, mixed> */
    private function membershipPayload(?OrganizationMemberDto $organizationMemberDto): ?array
    {
        if (! $organizationMemberDto instanceof OrganizationMemberDto) {
            return null;
        }

        return [
            'organization_id' => $organizationMemberDto->organizationId,
            'user_id'         => $organizationMemberDto->userId,
            'role'            => $organizationMemberDto->role,
        ];
    }

    private function buildFullUrl(ServerRequestInterface $serverRequest): string
    {
        $uri  = $serverRequest->getUri();
        $port = $uri->getPort();
        $url  = $uri->getScheme() . '://' . $uri->getHost();
        if (null !== $port && ! in_array($port, [80, 443], true)) {
            $url .= ':' . $port;
        }
        $url .= $uri->getPath();
        $query = $uri->getQuery();
        if ('' !== $query) {
            $url .= '?' . $query;
        }

        return $url;
    }
}
