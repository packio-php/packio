<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Authenticated]
#[Can('organizations.invitations.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post('/organization/:organization/invitations/:invitationId/revoke', name: 'organization.invitations.revoke')]
final readonly class OrganizationInvitationRevokeHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto      = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext         = RequestContextAttributes::requireCurrentOrganizationActorContext($request);
        $invitationId         = RouteAttributes::uuid($request, 'invitationId');

        $revoked = '' !== $invitationId && $this->organizationApiFacade->revokeInvitationInContext($actorContext, $invitationId);

        $this->inertiaNotificationMessenger->flash(
            $request,
            $revoked ? 'success' : 'error',
            $revoked ? 'Invitation revoked.' : 'Invitation not found.',
        );

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/members');
    }
}
