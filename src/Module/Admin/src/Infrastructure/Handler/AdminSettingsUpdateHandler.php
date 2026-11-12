<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use InvalidArgumentException;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Admin\Infrastructure\Request\AdminSettingsRequest;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/admin/settings', name: 'admin.settings.update')]
#[Authenticated]
#[Can('admin.access')]
#[MapRequest(body: AdminSettingsRequest::class)]
final readonly class AdminSettingsUpdateHandler implements RequestHandlerInterface
{
    public function __construct(
        private ProjectSettingsApiFacadeInterface $projectSettingsApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = RequestContextAttributes::currentUser($request);

        /** @var AdminSettingsRequest $form */
        $form = $request->getAttribute(AdminSettingsRequest::class);

        $updatedByUserId = $user instanceof UserDto ? $user->id : null;

        try {
            $this->projectSettingsApiFacade->updateOverrides($form->settings, $updatedByUserId);
            $this->inertiaNotificationMessenger->flash($request, 'success', 'Project settings updated.');
        } catch (InvalidArgumentException) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Project settings contain an invalid value.');
        }

        return new RedirectResponse('/admin/settings');
    }
}
