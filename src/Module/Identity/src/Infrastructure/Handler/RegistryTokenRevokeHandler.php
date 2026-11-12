<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Authenticated]
#[Can('tokens.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post('/organization/:organization/tokens/:tokenId/revoke', name: 'tokens.revoke')]
final readonly class RegistryTokenRevokeHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto      = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext         = RequestContextAttributes::requireCurrentOrganizationActorContext($request);
        $tokenId              = RouteAttributes::uuid($request, 'tokenId');

        $revoked = '' !== $tokenId
            && $this->identityApiFacade->revokeRegistryAccessTokenInContext($actorContext, $tokenId);

        $this->inertiaNotificationMessenger->flash(
            $request,
            $revoked ? 'success' : 'error',
            $revoked ? 'Registry token revoked.' : 'Registry token not found.',
        );

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/tokens');
    }
}
