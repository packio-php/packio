<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Infrastructure\Request\ProfileOAuthDisconnectRequest;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/profile/oauth/:accountId/disconnect', name: 'profile.oauth.disconnect')]
#[Authenticated]
#[MapRequest(route: ProfileOAuthDisconnectRequest::class)]
#[ValidateMappedRequest(ProfileOAuthDisconnectRequest::class)]
#[ValidationFailed(
    redirectTo: '/profile',
    message: 'Invalid OAuth account.',
    notify: ValidationNotify::Toast,
)]
final readonly class DisconnectOAuthAccountHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $currentUser = RequestContextAttributes::currentUser($request);
        if (! $currentUser instanceof UserDto) {
            return new RedirectResponse('/auth/login');
        }

        /** @var ProfileOAuthDisconnectRequest $route */
        $route = $request->getAttribute(ProfileOAuthDisconnectRequest::class);

        $result  = $this->identityApiFacade->disconnectOAuthAccount($currentUser->id, $route->accountId);
        $success = true === $result['success'];

        $this->inertiaNotificationMessenger->toast(
            $request,
            $success ? 'success' : 'error',
            $success ? 'OAuth sign-in method disconnected.' : ($result['message'] ?? 'OAuth account could not be disconnected.'),
        );

        return new RedirectResponse('/profile');
    }
}
