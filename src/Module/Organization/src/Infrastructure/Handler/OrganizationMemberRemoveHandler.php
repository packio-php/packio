<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Organization\Api\Exception\OrganizationExceptionInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Authenticated]
#[Can('organizations.members.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post('/organization/:organization/members/:memberId/remove', name: 'organization.members.remove')]
final readonly class OrganizationMemberRemoveHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto      = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext         = RequestContextAttributes::requireCurrentOrganizationActorContext($request);
        $memberId             = RouteAttributes::uuid($request, 'memberId');

        try {
            $removed = '' !== $memberId && $this->organizationApiFacade->removeMemberInContext($actorContext, $memberId);
        } catch (OrganizationExceptionInterface $exception) {
            $this->inertiaNotificationMessenger->flash($request, 'error', $exception->userMessage());

            return new RedirectResponse('/organization/' . $organizationDto->slug . '/members');
        }

        $this->inertiaNotificationMessenger->flash(
            $request,
            $removed ? 'success' : 'error',
            $removed ? 'Organization member removed.' : 'Organization member not found.',
        );

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/members');
    }
}
