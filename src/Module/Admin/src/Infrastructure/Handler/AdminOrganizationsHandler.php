<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Packio\Organization\Api\Dto\OrganizationSummaryDto;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function array_map;

#[Get('/admin/organizations', name: 'admin.organizations')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminOrganizationsHandler implements RequestHandlerInterface
{
    public function __construct(private OrganizationApiFacadeInterface $organizationApiFacade) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia            = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        $paginatedResultDto = $this->organizationApiFacade->organizationSummaries(PaginationCriteria::fromArray($request->getQueryParams()));
        $pagination         = $paginatedResultDto->toArray();
        unset($pagination['items']);

        return $inertia->render('Admin/Organizations', [
            // A closure replaces the shared `organizations` prop; an array would be merged into it index by index.
            'organizations' => static fn (): array => array_map(
                fn (OrganizationSummaryDto $organizationSummaryDto): array => [
                    'id'                => $organizationSummaryDto->id,
                    'slug'              => $organizationSummaryDto->slug,
                    'name'              => $organizationSummaryDto->name,
                    'anonymous_access'  => $organizationSummaryDto->anonymousAccess,
                    'active'            => $organizationSummaryDto->active,
                    'status'            => $organizationSummaryDto->status,
                    'created_at'        => $organizationSummaryDto->createdAt,
                    'updated_at'        => $organizationSummaryDto->updatedAt,
                    'members_count'     => $organizationSummaryDto->memberCount,
                    'invitations_count' => $organizationSummaryDto->invitationCount,
                ],
                $paginatedResultDto->items,
            ),
            'pagination'    => $pagination,
        ]);
    }
}
