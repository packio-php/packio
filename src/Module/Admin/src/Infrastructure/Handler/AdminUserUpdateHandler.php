<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Common\Exception\PublicValidationException;
use InvalidArgumentException;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Admin\Infrastructure\Request\AdminUserUpdateRequest;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/admin/users/:userId', name: 'admin.users.update')]
#[Authenticated]
#[Can('admin.access')]
#[MapRequest(body: AdminUserUpdateRequest::class)]
#[ValidateMappedRequest(AdminUserUpdateRequest::class)]
#[ValidationFailed(
    redirectTo: '/admin/users/{userId}',
    message: 'Invalid request data.',
    notify: ValidationNotify::Flash,
)]
final readonly class AdminUserUpdateHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = RouteAttributes::uuid($request, 'userId');

        if ('' === $userId) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'User not found.');

            return new RedirectResponse('/admin/users');
        }

        /** @var AdminUserUpdateRequest $form */
        $form = $request->getAttribute(AdminUserUpdateRequest::class);

        try {
            $updated = $this->identityApiFacade->updateUser(
                $userId,
                $form->email,
                $form->displayName,
                $form->normalizedRoles(),
            );
        } catch (PublicValidationException $exception) {
            $this->inertiaNotificationMessenger->flash($request, 'error', $exception->getMessage());

            return new RedirectResponse('/admin/users/' . $userId);
        } catch (InvalidArgumentException) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Unable to update user.');

            return new RedirectResponse('/admin/users/' . $userId);
        }

        if (! $updated instanceof UserDto) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'User not found.');

            return new RedirectResponse('/admin/users');
        }

        $this->inertiaNotificationMessenger->flash($request, 'success', 'User updated.');

        return new RedirectResponse('/admin/users/' . $userId);
    }
}
