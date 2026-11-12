<?php

declare(strict_types=1);

namespace Packio\Application\Error;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RequestPresentationPolicy;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Rbac\Exception\AuthorizationException;

final readonly class AuthorizationExceptionResponseFactory
{
    private const string ORGANIZATION_VIEW_PERMISSION               = 'organizations.view';
    private const string ORGANIZATION_MANAGE_PERMISSION             = 'organizations.manage';
    private const string PACKAGES_VIEW_PERMISSION                   = 'packages.view';
    private const string TOKENS_VIEW_PERMISSION                     = 'tokens.view';
    private const string TOKENS_MANAGE_PERMISSION                   = 'tokens.manage';
    private const string SOURCES_VIEW_PERMISSION                    = 'sources.view';
    private const string SOURCES_MANAGE_PERMISSION                  = 'sources.manage';
    private const string ORGANIZATION_MEMBERS_VIEW_PERMISSION       = 'organizations.members.view';
    private const string ORGANIZATION_MEMBERS_MANAGE_PERMISSION     = 'organizations.members.manage';
    private const string ORGANIZATION_INVITATIONS_MANAGE_PERMISSION = 'organizations.invitations.manage';
    private const string PACKAGES_MANAGE_PERMISSION                 = 'packages.manage';
    private const string WEBHOOKS_MANAGE_PERMISSION                 = 'webhooks.manage';

    public function __construct(
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private RequestPresentationPolicy $requestPresentationPolicy = new RequestPresentationPolicy(),
    ) {}

    public function create(AuthorizationException $authorizationException, ServerRequestInterface $serverRequest): ?ResponseInterface
    {
        if ($this->wantsJson($serverRequest)) {
            return $this->withAuthorizationHeaders(
                new JsonResponse([
                    'error' => $authorizationException->getPublicMessage(),
                ], 403),
                $authorizationException,
            );
        }

        return match ($authorizationException->getPermission()) {
            self::ORGANIZATION_MANAGE_PERMISSION,
            self::TOKENS_MANAGE_PERMISSION,
            self::SOURCES_MANAGE_PERMISSION,
            self::ORGANIZATION_MEMBERS_MANAGE_PERMISSION,
            self::ORGANIZATION_INVITATIONS_MANAGE_PERMISSION,
            self::PACKAGES_MANAGE_PERMISSION,
            self::WEBHOOKS_MANAGE_PERMISSION            => $this->organizationRedirect(
                $serverRequest,
                $authorizationException,
                $this->currentOrganizationPath($serverRequest),
                'Owner access is required.',
            ),
            self::ORGANIZATION_VIEW_PERMISSION,
            self::TOKENS_VIEW_PERMISSION,
            self::SOURCES_VIEW_PERMISSION,
            self::ORGANIZATION_MEMBERS_VIEW_PERMISSION,
            self::PACKAGES_VIEW_PERMISSION              => $this->organizationRedirect(
                $serverRequest,
                $authorizationException,
                '/organizations',
                'Access denied.',
            ),
            default                                     => null,
        };
    }

    private function organizationRedirect(
        ServerRequestInterface $serverRequest,
        AuthorizationException $authorizationException,
        string $location,
        string $message,
    ): ResponseInterface {
        $this->inertiaNotificationMessenger->flash($serverRequest, 'error', $message);

        return $this->withAuthorizationHeaders(new RedirectResponse($location), $authorizationException);
    }

    private function currentOrganizationPath(ServerRequestInterface $serverRequest): string
    {
        $organization = RequestContextAttributes::currentOrganization($serverRequest);
        if ($organization instanceof OrganizationDto) {
            return '/organization/' . $organization->slug;
        }

        $contextOrganization = RequestContextAttributes::context($serverRequest)->currentOrganization;
        if ($contextOrganization instanceof OrganizationDto) {
            return '/organization/' . $contextOrganization->slug;
        }

        return '/organizations';
    }

    private function wantsJson(ServerRequestInterface $serverRequest): bool
    {
        return $this->requestPresentationPolicy->wantsJson($serverRequest);
    }

    private function withAuthorizationHeaders(
        ResponseInterface $response,
        AuthorizationException $authorizationException
    ): ResponseInterface {
        foreach ($authorizationException->getHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
