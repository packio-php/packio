<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Packio\Identity\Api\Dto\UserListFilterDto;
use Packio\Identity\Api\Dto\UserListStatus;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function array_map;
use function in_array;

#[Get('/admin/users', name: 'admin.users')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminUsersHandler implements RequestHandlerInterface
{
    public function __construct(private IdentityApiFacadeInterface $identityApiFacade) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia              = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        $queryParams          = $request->getQueryParams();
        $paginationCriteria   = PaginationCriteria::fromArray($queryParams);
        $userListFilterDto    = UserListFilterDto::fromArray($queryParams);
        $paginatedResultDto   = $this->identityApiFacade->findUsersPaginated($paginationCriteria, $userListFilterDto);

        return $inertia->render('Admin/Users', [
            'users'      => array_map(
                fn (UserDto $userDto): array => [
                    'id'             => $userDto->id,
                    'email'          => $userDto->email,
                    'display_name'   => $userDto->displayName,
                    'roles'          => $userDto->roles,
                    'created_at'     => $userDto->createdAt,
                    'updated_at'     => $userDto->updatedAt,
                    'is_admin'       => in_array('admin', $userDto->roles, true),
                    'email_verified' => $userDto->isEmailVerified,
                    'is_banned'      => $userDto->isBanned,
                    'banned_at'      => $userDto->bannedAt,
                    'ban_reason'     => $userDto->banReason,
                ],
                $paginatedResultDto->items,
            ),
            'pagination' => $paginatedResultDto->toArray(),
            'filters'    => [
                'search'   => $paginationCriteria->search ?? '',
                'status'   => $userListFilterDto->status instanceof UserListStatus ? $userListFilterDto->status->value : '',
                'page'     => $paginatedResultDto->currentPage,
                'per_page' => $paginationCriteria->perPage,
            ],
        ]);
    }
}
