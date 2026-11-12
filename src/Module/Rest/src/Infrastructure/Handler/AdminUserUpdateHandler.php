<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Common\Exception\PublicValidationException;
use InvalidArgumentException;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Rest\Attribute\ApiAudited;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Request\UpdateAdminUserRequest;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\UserResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Patch;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Patch('/api/v1/admin/users/:userId', name: 'api.v1.admin.users.update')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[RateLimit(
    key: 'api_admin_write',
    by: ['ip'],
    response: 'json',
    message: 'Too many admin API write requests. Please try again later.'
)]
#[MapRequest(source: UpdateAdminUserRequest::class)]
#[ValidateMappedRequest(UpdateAdminUserRequest::class)]
#[ApiAudited]
#[Can('admin.access')]
final readonly class AdminUserUpdateHandler implements RequestHandlerInterface
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

        /** @var UpdateAdminUserRequest $form */
        $form  = $request->getAttribute(UpdateAdminUserRequest::class);
        $email = $form->email($user->email);

        $displayName = $form->displayName($user->displayName);

        try {
            $updated = $this->identityApiFacade->updateUser(
                $user->id,
                $email,
                $displayName,
                $form->roles($user->roles),
            );
        } catch (PublicValidationException $exception) {
            return $this->apiErrorResponseFactory->validation([
                'roles' => [$exception->getMessage()],
            ]);
        } catch (InvalidArgumentException $exception) {
            // The request validates email format and name; Identity's fixed email messages remain here.
            return $this->apiErrorResponseFactory->validation([
                'email' => [$exception->getMessage()],
            ]);
        }

        if (! $updated instanceof UserDto) {
            return $this->apiErrorResponseFactory->notFound('User not found.');
        }

        return $this->apiResponseFactory->json([
            'data' => UserResource::fromDto($updated),
        ]);
    }
}
