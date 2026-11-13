<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Organization\Api\Dto\OrganizationInvitationDto;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Request\OrganizationInviteAcceptRequest;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

use function http_build_query;
use function rawurlencode;

#[Authenticated]
#[Post('/organization/invite/:token/accept', name: 'organization.invite.accept.submit')]
#[MapRequest(route: OrganizationInviteAcceptRequest::class)]
#[ValidateMappedRequest(OrganizationInviteAcceptRequest::class)]
#[ValidationFailed(
    redirectTo: '/organizations',
    message: 'Invitation not found.',
    notify: ValidationNotify::Flash,
)]
final readonly class OrganizationInviteAcceptSubmitHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var OrganizationInviteAcceptRequest $route */
        $route = $request->getAttribute(OrganizationInviteAcceptRequest::class);
        $token = $route->token;

        $invitation = $this->organizationApiFacade->findInvitationByToken($token);
        if (! $invitation instanceof OrganizationInvitationDto) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Invitation not found.');

            return new RedirectResponse('/organizations');
        }

        $organization = $this->organizationApiFacade->findOrganization($invitation->organizationId);
        $redirect     = $organization instanceof OrganizationDto
            ? '/organization/' . $organization->slug . '/packages'
            : '/organizations';

        if ('pending' !== $invitation->status) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Invitation is ' . $invitation->status . '.');

            return new RedirectResponse($this->inviteTarget($token));
        }

        $userId = RequestContextAttributes::currentUserId($request) ?? '';

        if ('' === $userId) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Sign in to accept this invitation.');

            return new RedirectResponse('/auth/login?' . http_build_query([
                'redirect_to' => $this->inviteTarget($token),
            ]));
        }

        $accepted = $this->organizationApiFacade->acceptInvitation($token, $userId);
        if (! $accepted instanceof OrganizationInvitationDto || 'accepted' !== $accepted->status) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Invitation could not be accepted.');

            return new RedirectResponse($this->inviteTarget($token));
        }

        $this->inertiaNotificationMessenger->flash($request, 'success', 'Invitation accepted.');

        return new RedirectResponse($redirect);
    }

    private function inviteTarget(string $token): string
    {
        return '/organization/invite/' . rawurlencode($token);
    }
}
