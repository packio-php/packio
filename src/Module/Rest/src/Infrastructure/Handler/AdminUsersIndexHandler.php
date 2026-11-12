<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Identity\Api\Dto\UserListFilterDto;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\UserResource;
use Packio\Rest\Infrastructure\Response\PaginationResponseFactory;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function array_filter;

#[Get('/api/v1/admin/users', name: 'api.v1.admin.users.index')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[Can('admin.access')]
final readonly class AdminUsersIndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private PaginationResponseFactory $paginationResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams          = $request->getQueryParams();
        $paginationCriteria   = PaginationCriteria::fromArray($queryParams);
        $userListFilterDto    = UserListFilterDto::fromArray($queryParams);
        $paginatedResultDto   = $this->identityApiFacade->findUsersPaginated(
            $paginationCriteria,
            $userListFilterDto,
        );

        $serverRequest = $request->withQueryParams(array_filter([
            'search' => $paginationCriteria->search,
            'status' => $userListFilterDto->status?->value,
        ], static fn (?string $value): bool => null !== $value));

        return $this->paginationResponseFactory->fromPaginatedResult(
            $serverRequest,
            $paginatedResultDto,
            static fn ($user): array => UserResource::fromDto($user),
        );
    }
}
