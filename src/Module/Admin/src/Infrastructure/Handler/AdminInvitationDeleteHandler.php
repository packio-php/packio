<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

use function is_string;

#[Post('/admin/organizations/:organizationId/invitations/:invitationId/delete', name: 'admin.organizations.invitations.delete')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminInvitationDeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private AuditApiFacadeInterface $auditApiFacade,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = $request->getAttribute('organizationId');

        if (! is_string($organizationId) || '' === $organizationId) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Organization not found.');

            return new RedirectResponse('/admin/organizations');
        }

        // the redirect keeps the raw route value; only the facade needs a canonical UUID
        $organizationUuid = RouteAttributes::uuid($request, 'organizationId');
        $invitationId     = RouteAttributes::uuid($request, 'invitationId');
        $deleted          = '' !== $organizationUuid
            && '' !== $invitationId
            && $this->organizationApiFacade->deleteInvitation($organizationUuid, $invitationId);

        if ($deleted) {
            $this->auditApiFacade->record(
                event: 'organization.invitation.deleted_by_admin',
                context: [],
                organizationId: $organizationUuid,
                actorId: RequestContextAttributes::currentUserId($request) ?? '',
                subjectType: 'organization_invitation',
                subjectId: $invitationId,
            );
        }

        $this->inertiaNotificationMessenger->flash(
            $request,
            $deleted ? 'success' : 'error',
            $deleted ? 'Invitation deleted.' : 'Only non-pending invitations can be deleted.',
        );

        return new RedirectResponse('/admin/organizations/' . $organizationId);
    }
}
