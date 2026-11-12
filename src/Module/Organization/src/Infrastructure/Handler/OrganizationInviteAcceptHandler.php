<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Organization\Api\Dto\OrganizationInvitationDto;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Request\OrganizationInviteAcceptRequest;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

use function http_build_query;
use function rawurlencode;

#[Get('/organization/invite/:token', name: 'organization.invite.accept')]
#[MapRequest(route: OrganizationInviteAcceptRequest::class)]
#[ValidateMappedRequest(OrganizationInviteAcceptRequest::class)]
final readonly class OrganizationInviteAcceptHandler implements RequestHandlerInterface
{
    public function __construct(private OrganizationApiFacadeInterface $organizationApiFacade) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var InertiaInterface $inertia */
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        /** @var OrganizationInviteAcceptRequest $route */
        $route = $request->getAttribute(OrganizationInviteAcceptRequest::class);

        if ('' === $this->currentUserId($request)) {
            return new RedirectResponse('/auth/login?' . http_build_query([
                'redirect_to' => $this->inviteTarget($route->token),
            ]));
        }

        $invitation   = $this->organizationApiFacade->findInvitationByToken($route->token);
        $organization = $invitation instanceof OrganizationInvitationDto
            ? $this->organizationApiFacade->findOrganization($invitation->organizationId)
            : null;

        return $inertia->render('Organization/InviteAccept', [
            // The stored token may not decrypt after a key change; the route token is the link token.
            'invitation'          => $invitation instanceof OrganizationInvitationDto
                ? [
                    ...$invitation->toArray(),
                    'token' => $route->token,
                ]
                : null,
            'organization_target' => $organization instanceof OrganizationDto ? $organization->toArray() : null,
        ]);
    }

    private function currentUserId(ServerRequestInterface $serverRequest): string
    {
        return RequestContextAttributes::currentUserId($serverRequest) ?? '';
    }

    private function inviteTarget(string $token): string
    {
        return '/organization/invite/' . rawurlencode($token);
    }
}
