<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Organization\Api\Exception\OrganizationExceptionInterface;
use Packio\Organization\Api\Exception\OrganizationNotFoundException;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Request\OrganizationSettingsRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/organization/:organization/settings', name: 'organization.settings.update')]
#[Authenticated]
#[Can('organizations.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[MapRequest(body: OrganizationSettingsRequest::class)]
#[ValidateMappedRequest(OrganizationSettingsRequest::class)]
#[ValidationFailed(
    redirectTo: '/organization/{organization}/settings',
    message: 'Invalid request data.',
    notify: ValidationNotify::Toast,
)]
final readonly class OrganizationSettingsUpdateHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private ValidationRedirectState $validationRedirectState = new ValidationRedirectState()
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext    = RequestContextAttributes::requireCurrentOrganizationActorContext($request);

        /** @var OrganizationSettingsRequest $form */
        $form = $request->getAttribute(OrganizationSettingsRequest::class);

        $anonymousAccessEnabled = '1' === $form->anonymousAccess;

        try {
            $this->organizationApiFacade->updateOrganizationSettingsInContext(
                $actorContext,
                $form->name,
                $anonymousAccessEnabled,
            );
        } catch (OrganizationExceptionInterface $exception) {
            if ($exception instanceof OrganizationNotFoundException) {
                $this->inertiaNotificationMessenger->flash($request, 'error', $exception->userMessage());

                return new RedirectResponse('/organizations');
            }

            $this->validationRedirectState->errors($request, [
                'name' => [$exception->userMessage()],
            ]);
            $this->validationRedirectState->old($request, [
                'name'             => $form->name,
                'anonymous_access' => $form->anonymousAccess,
            ]);

            return new RedirectResponse('/organization/' . $organizationDto->slug . '/settings');
        }

        $this->inertiaNotificationMessenger->flash(
            $request,
            'success',
            'Organization settings updated.',
        );

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/settings');
    }
}
