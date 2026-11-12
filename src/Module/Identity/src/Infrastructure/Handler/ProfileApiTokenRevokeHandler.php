<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Post('/profile/api-tokens/:tokenId/revoke', name: 'profile.api-tokens.revoke')]
#[Authenticated]
final readonly class ProfileApiTokenRevokeHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $currentUser = RequestContextAttributes::currentUser($request);
        if (! $currentUser instanceof UserDto) {
            return new RedirectResponse('/auth/login');
        }

        $tokenId = RouteAttributes::uuid($request, 'tokenId');
        if ('' === $tokenId) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'API token not found.');

            return new RedirectResponse('/profile');
        }

        $revoked = $this->identityApiFacade->revokeApiAccessTokenForUser($currentUser->id, $tokenId);

        $this->inertiaNotificationMessenger->toast(
            $request,
            $revoked ? 'success' : 'error',
            $revoked ? 'API token revoked.' : 'API token not found.',
        );

        return new RedirectResponse('/profile');
    }
}
