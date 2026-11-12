<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Rest\Attribute\ApiAudited;
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
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Post('/api/v1/admin/users/:userId/verify', name: 'api.v1.admin.users.verify')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[ApiAudited]
#[RateLimit(key: 'api_admin_write', by: ['ip', 'user'], response: 'json', message: 'Too many admin API write requests. Please try again later.')]
#[Can('admin.access')]
final readonly class AdminUserVerifyHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = ApiRouteResourceResolver::userId($request);
        if ('' === $userId || ! $this->identityApiFacade->markUserEmailAsVerified($userId)) {
            return $this->apiErrorResponseFactory->notFound('User not found.');
        }

        $user = $this->identityApiFacade->findUserById($userId);
        if (! $user instanceof UserDto) {
            return $this->apiErrorResponseFactory->notFound('User not found.');
        }

        return $this->apiResponseFactory->json([
            'data' => UserResource::fromDto($user),
        ]);
    }
}
