<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Handler;

use Common\Branding\BrandingConfig;
use Mezzio\Router\RouterInterface;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Packio\Organization\Application\Query\OrganizationMembersViewQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function rawurlencode;
use function rtrim;

#[Authenticated]
#[Can('organizations.members.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get(
    '/organization/:organization/members',
    name: 'organization.members',
    middleware: [RequireExistingOrganizationMiddleware::class]
)]
final readonly class OrganizationMembersHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationMembersViewQuery $organizationMembersViewQuery,
        private RouterInterface $router,
        private BrandingConfig $brandingConfig,
        private OrganizationPolicy $organizationPolicy,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var InertiaInterface $inertia */
        $inertia         = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);

        $organizationMembersView = $this->organizationMembersViewQuery->fetch(
            $organizationDto->id,
            RequestContextAttributes::currentUserId($request),
            $this->organizationPolicy->canManage($organizationDto, RequestContextAttributes::currentMembership($request)),
        );
        $invitationItems = [];
        // An invitation link grants the role it carries, so only managers may see it.
        foreach ($organizationMembersView->canManageMembers ? $organizationMembersView->invitations : [] as $invitation) {
            // '' means the stored token cannot be decrypted (key rotated): list the invitation without a link.
            if ('' !== $invitation['token']) {
                $invitation['accept_url'] = $this->invitationAcceptUrl($invitation['token']);
            }
            unset($invitation['token']);
            $invitationItems[] = $invitation;
        }

        return $inertia->render('Organization/Members', [
            'organization'       => [
                'id'   => $organizationDto->id,
                'slug' => $organizationDto->slug,
                'name' => $organizationDto->name,
            ],
            'members'            => $organizationMembersView->members,
            'invitations'        => $invitationItems,
            'can_manage_members' => $organizationMembersView->canManageMembers,
            'owners_count'       => $organizationMembersView->ownersCount,
        ]);
    }

    private function invitationAcceptUrl(string $token): string
    {
        return rtrim($this->brandingConfig->appBaseUrl(), '/') . $this->router->generateUri('organization.invite.accept', [
            'token' => rawurlencode($token),
        ]);
    }
}
