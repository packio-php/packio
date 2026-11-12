<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\UserResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/admin/users/:userId', name: 'api.v1.admin.users.show')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[Can('admin.access')]
final readonly class AdminUserShowHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = ApiRouteResourceResolver::userId($request);
        $user   = '' !== $userId ? $this->identityApiFacade->findUserById($userId) : null;
        if (! $user instanceof UserDto) {
            return $this->apiErrorResponseFactory->notFound('User not found.');
        }

        return $this->apiResponseFactory->json([
            'data' => UserResource::fromDto($user),
        ]);
    }
}
