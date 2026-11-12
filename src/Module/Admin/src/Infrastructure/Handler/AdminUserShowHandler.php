<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Admin\Infrastructure\UserModeration\UserModerationService;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function array_map;
use function in_array;
use function is_string;

#[Get('/admin/users/:userId', name: 'admin.users.show')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminUserShowHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private UserModerationService $userModerationService,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        $userId  = RouteAttributes::uuid($request, 'userId');

        if ('' === $userId) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'User not found.');

            return new RedirectResponse('/admin/users');
        }

        $user = $this->identityApiFacade->findUserById($userId);
        if (! $user instanceof UserDto) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'User not found.');

            return new RedirectResponse('/admin/users');
        }

        $currentUserId     = RequestContextAttributes::currentUserId($request);
        $activeAdminCount  = $this->identityApiFacade->activeAdminsCount();
        $isCurrentUser     = $currentUserId === $user->id;
        $isLastActiveAdmin = ! $user->isBanned && in_array('admin', $user->roles, true) && $activeAdminCount <= 1;

        return $inertia->render('Admin/UserDetails', [
            'user_record' => [
                'id'                => $user->id,
                'email'             => $user->email,
                'display_name'      => $user->displayName,
                'roles'             => $user->roles,
                'created_at'        => $user->createdAt,
                'updated_at'        => $user->updatedAt,
                'is_admin'          => in_array('admin', $user->roles, true),
                'email_verified'    => $user->isEmailVerified,
                'is_banned'         => $user->isBanned,
                'banned_at'         => $user->bannedAt,
                'ban_reason'        => $user->banReason,
                'banned_by_user_id' => $user->bannedByUserId,
                'can_ban'           => ! $isCurrentUser && ! $isLastActiveAdmin && ! $user->isBanned,
                'moderation_hint'   => $isCurrentUser
                    ? 'You cannot ban your own account.'
                    : ($isLastActiveAdmin ? 'The last active administrator cannot be banned.' : null),
            ],
            'ban_history' => array_map(
                static fn ($entry): array => [
                    'id'          => $entry->id,
                    'action'      => $entry->event,
                    'occurred_at' => $entry->occurredAt,
                    'actor_id'    => $entry->actorId,
                    'reason'      => is_string($entry->context['reason'] ?? null)
                        ? $entry->context['reason']
                        : (is_string($entry->context['note'] ?? null) ? $entry->context['note'] : null),
                ],
                $this->userModerationService->history($user->id),
            ),
        ]);
    }
}
