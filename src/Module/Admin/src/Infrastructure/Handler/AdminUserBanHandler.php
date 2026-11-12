<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Admin\Infrastructure\Request\AdminUserBanRequest;
use Packio\Admin\Infrastructure\UserModeration\UserModerationService;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RequestPresentationPolicy;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Identity\Api\Dto\UserModerationOutcome;
use Packio\Identity\Api\Dto\UserModerationResultDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/admin/users/:userId/ban', name: 'admin.users.ban')]
#[Authenticated]
#[Can('admin.access')]
#[MapRequest(body: AdminUserBanRequest::class)]
#[ValidateMappedRequest(AdminUserBanRequest::class)]
#[ValidationFailed(
    redirectTo: '/admin/users/{userId}',
    message: 'Invalid ban reason.',
    notify: ValidationNotify::Flash,
)]
final readonly class AdminUserBanHandler implements RequestHandlerInterface
{
    public function __construct(
        private UserModerationService $userModerationService,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private RequestPresentationPolicy $requestPresentationPolicy = new RequestPresentationPolicy(),
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = RouteAttributes::uuid($request, 'userId');
        if ('' === $userId) {
            return $this->notFound($request);
        }

        $adminUserId = RequestContextAttributes::currentUserId($request);
        if (null === $adminUserId) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Admin access is required.');

            return new RedirectResponse('/');
        }

        /** @var AdminUserBanRequest $form */
        $form = $request->getAttribute(AdminUserBanRequest::class);

        return $this->respond($request, $userId, $this->userModerationService->ban($adminUserId, $userId, $form->normalizedReason()));
    }

    private function respond(
        ServerRequestInterface $serverRequest,
        string $userId,
        UserModerationResultDto $userModerationResultDto
    ): ResponseInterface {
        $message = match ($userModerationResultDto->outcome) {
            UserModerationOutcome::Success          => ['success', 'User banned.'],
            UserModerationOutcome::UserNotFound     => ['error', 'User not found.'],
            UserModerationOutcome::SelfBanForbidden => ['error', 'You cannot ban your own account.'],
            UserModerationOutcome::LastActiveAdmin  => ['error', 'The last active administrator cannot be banned.'],
            UserModerationOutcome::AlreadyBanned    => ['error', 'User is already banned.'],
            UserModerationOutcome::NotBanned        => ['error', 'Unable to ban user.'],
        };

        if ($this->wantsJson($serverRequest)) {
            return new JsonResponse([
                'message' => $message[1],
            ], $this->status($userModerationResultDto->outcome));
        }
        $this->inertiaNotificationMessenger->flash($serverRequest, $message[0], $message[1]);

        return new RedirectResponse(
            UserModerationOutcome::UserNotFound === $userModerationResultDto->outcome ? '/admin/users' : '/admin/users/' . $userId,
        );
    }

    private function notFound(ServerRequestInterface $serverRequest): RedirectResponse
    {
        $this->inertiaNotificationMessenger->flash($serverRequest, 'error', 'User not found.');

        return new RedirectResponse('/admin/users');
    }

    private function status(UserModerationOutcome $userModerationOutcome): int
    {
        return match ($userModerationOutcome) {
            UserModerationOutcome::Success      => 200,
            UserModerationOutcome::UserNotFound => 404,
            default                             => 409,
        };
    }

    private function wantsJson(ServerRequestInterface $serverRequest): bool
    {
        return $this->requestPresentationPolicy->wantsJson($serverRequest);
    }
}
