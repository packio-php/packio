<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function array_column;
use function array_combine;
use function array_map;

#[Get('/admin/organizations/:organizationId', name: 'admin.organizations.show')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminOrganizationShowHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private IdentityApiFacadeInterface $identityApiFacade,
        private SourceApiFacadeInterface $sourceApiFacade,
        private PackageApiFacadeInterface $packageApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia        = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        $organizationId = RouteAttributes::uuid($request, 'organizationId');

        if ('' === $organizationId) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Organization not found.');

            return new RedirectResponse('/admin/organizations');
        }

        $organization = $this->organizationApiFacade->findOrganization($organizationId);
        if (! $organization instanceof OrganizationDto) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Organization not found.');

            return new RedirectResponse('/admin/organizations');
        }

        $queryParams        = $request->getQueryParams();
        $paginationCriteria = PaginationCriteria::fromArray([
            'page'     => $queryParams['members_page'] ?? null,
            'per_page' => $queryParams['members_per_page'] ?? null,
        ]);
        $paginatedResultDto = $this->organizationApiFacade->findMembersPaginated(
            $organization->id,
            $paginationCriteria,
        );
        $users   = $this->identityApiFacade->findUsersByIds(array_column($paginatedResultDto->items, 'userId'));
        $userMap = array_combine(array_column($users, 'id'), $users);

        return $inertia->render('Admin/OrganizationDetails', [
            'organization_record' => [
                'id'                 => $organization->id,
                'slug'               => $organization->slug,
                'name'               => $organization->name,
                'anonymous_access'   => $organization->anonymousAccess,
                'active'             => $organization->active,
                'status'             => $organization->status,
                'created_at'         => $organization->createdAt,
                'updated_at'         => $organization->updatedAt,
                'members_count'      => $paginatedResultDto->total,
                'invitations_count'  => $this->organizationApiFacade->invitationsCount($organization->id),
                'sources_count'      => $this->sourceApiFacade->countForOrganization($organization->id),
                'packages_count'     => $this->packageApiFacade->countForOrganization($organization->id),
                'members'            => array_map(static function($member) use ($userMap): array {
                    $user = $userMap[$member->userId] ?? null;

                    return [
                        'id'           => $member->id,
                        'user_id'      => $member->userId,
                        'display_name' => $user->displayName ?? 'Unknown user',
                        'email'        => $user->email ?? 'unknown@example.com',
                        'role'         => $member->role,
                        'created_at'   => $member->createdAt,
                    ];
                }, $paginatedResultDto->items),
                'members_pagination' => $paginatedResultDto->toArray(),
            ],
        ]);
    }
}
