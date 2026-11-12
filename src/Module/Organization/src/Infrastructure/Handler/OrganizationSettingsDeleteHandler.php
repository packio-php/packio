<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationOutcome;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Request\OrganizationDeleteRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

use function in_array;

#[Authenticated]
#[Can('organizations.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post(
    '/organization/:organization/settings/delete',
    name: 'organization.settings.delete',
)]
#[MapRequest(body: OrganizationDeleteRequest::class)]
#[ValidateMappedRequest(OrganizationDeleteRequest::class)]
#[ValidationFailed(
    redirectTo: '/organization/{organization}/settings',
    message: 'Invalid request data.',
    notify: ValidationNotify::Toast,
)]
final readonly class OrganizationSettingsDeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext    = RequestContextAttributes::requireCurrentOrganizationActorContext($request);

        /** @var OrganizationDeleteRequest $form */
        $form = $request->getAttribute(OrganizationDeleteRequest::class);

        // Business confirmation depends on the resolved organization, not only on the mapped DTO.
        if ($form->confirmationName !== $organizationDto->name) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Type the exact organization name to confirm deletion.');

            return new RedirectResponse('/organization/' . $organizationDto->slug . '/settings');
        }

        $organizationDeletionInitiationResult = $this->organizationApiFacade->deleteOrganizationInContextWithOutcome($actorContext);
        $queued                               = in_array($organizationDeletionInitiationResult->outcome, [
            OrganizationDeletionInitiationOutcome::Initiated,
            OrganizationDeletionInitiationOutcome::AlreadyInProgress,
        ], true);
        $this->inertiaNotificationMessenger->flash(
            $request,
            $queued ? 'success' : 'error',
            $queued ? 'Organization will be permanently deleted.' : 'Organization not found.',
        );

        return new RedirectResponse('/organizations');
    }
}
