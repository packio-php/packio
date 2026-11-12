<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Organization\Api\Exception\OrganizationExceptionInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Request\OrganizationMemberRoleUpdateRequest;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Authenticated]
#[Can('organizations.members.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post('/organization/:organization/members/:memberId/role', name: 'organization.members.role.update')]
#[MapRequest(body: OrganizationMemberRoleUpdateRequest::class)]
#[ValidateMappedRequest(OrganizationMemberRoleUpdateRequest::class)]
#[ValidationFailed(
    redirectTo: '/organization/{organization}/members',
    message: 'Invalid request data.',
    notify: ValidationNotify::Toast,
)]
final readonly class OrganizationMemberRoleUpdateHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto      = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext         = RequestContextAttributes::requireCurrentOrganizationActorContext($request);
        $memberId             = RouteAttributes::requireString($request, 'memberId');

        /** @var OrganizationMemberRoleUpdateRequest $form */
        $form = $request->getAttribute(OrganizationMemberRoleUpdateRequest::class);

        try {
            $updated = $this->organizationApiFacade->updateMemberRoleInContext(
                $actorContext,
                $memberId,
                $form->role,
            );
        } catch (OrganizationExceptionInterface $exception) {
            $this->inertiaNotificationMessenger->flash($request, 'error', $exception->userMessage());

            return new RedirectResponse('/organization/' . $organizationDto->slug . '/members');
        }

        $this->inertiaNotificationMessenger->flash(
            $request,
            $updated instanceof OrganizationMemberDto ? 'success' : 'error',
            $updated instanceof OrganizationMemberDto ? 'Organization member role updated.' : 'Organization member not found.',
        );

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/members');
    }
}
