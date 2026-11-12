<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Request\InvitationCreateRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Authenticated]
#[Can('organizations.invitations.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post('/organization/:organization/invitations', name: 'organization.invitations.create')]
#[MapRequest(body: InvitationCreateRequest::class)]
#[ValidateMappedRequest(InvitationCreateRequest::class)]
#[ValidationFailed(
    redirectTo: '/organization/{organization}/members',
    message: 'Invalid request data.',
    notify: ValidationNotify::Toast,
)]
final readonly class OrganizationInvitationCreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext    = RequestContextAttributes::requireCurrentOrganizationActorContext($request);

        /** @var InvitationCreateRequest $form */
        $form = $request->getAttribute(InvitationCreateRequest::class);

        $this->organizationApiFacade->createInvitationInContext($actorContext, $form->role);
        $this->inertiaNotificationMessenger->flash($request, 'success', 'Invitation created.');

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/members');
    }
}
