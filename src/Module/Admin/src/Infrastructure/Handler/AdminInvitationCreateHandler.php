<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Admin\Infrastructure\Request\AdminInvitationCreateRequest;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/admin/organizations/:organizationId/invitations', name: 'admin.organizations.invitations.create')]
#[Authenticated]
#[Can('admin.access')]
#[MapRequest(body: AdminInvitationCreateRequest::class)]
#[ValidateMappedRequest(AdminInvitationCreateRequest::class)]
#[ValidationFailed(
    redirectTo: '/admin/organizations/{organizationId}',
    message: 'Invalid request data.',
    notify: ValidationNotify::Flash,
)]
final readonly class AdminInvitationCreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = RouteAttributes::uuid($request, 'organizationId');

        if ('' === $organizationId) {
            return $this->deny($request);
        }

        $user = RequestContextAttributes::currentUser($request);
        if (! $user instanceof UserDto) {
            return $this->deny($request);
        }

        /** @var AdminInvitationCreateRequest $form */
        $form = $request->getAttribute(AdminInvitationCreateRequest::class);

        try {
            $this->organizationApiFacade->createInvitation($organizationId, $user->id, $form->role);
        } catch (RuntimeException) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Unable to create invitation.');

            return new RedirectResponse('/admin/organizations/' . $organizationId);
        }

        $this->inertiaNotificationMessenger->flash($request, 'success', 'Invitation created.');

        return new RedirectResponse('/admin/organizations/' . $organizationId);
    }

    private function deny(ServerRequestInterface $serverRequest): RedirectResponse
    {
        $this->inertiaNotificationMessenger->flash($serverRequest, 'error', 'Admin access is required.');

        return new RedirectResponse('/', 302);
    }
}
