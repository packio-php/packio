<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Admin\Infrastructure\Request\AdminOrganizationUpdateRequest;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Organization\Api\Exception\OrganizationExceptionInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/admin/organizations/:organizationId', name: 'admin.organizations.update')]
#[Authenticated]
#[Can('admin.access')]
#[MapRequest(body: AdminOrganizationUpdateRequest::class)]
#[ValidateMappedRequest(AdminOrganizationUpdateRequest::class)]
#[ValidationFailed(
    redirectTo: '/admin/organizations/{organizationId}',
    message: 'Invalid request data.',
    notify: ValidationNotify::Flash,
)]
final readonly class AdminOrganizationUpdateHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private AuditApiFacadeInterface $auditApiFacade,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = RouteAttributes::uuid($request, 'organizationId');

        if ('' === $organizationId) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Organization not found.');

            return new RedirectResponse('/admin/organizations');
        }

        /** @var AdminOrganizationUpdateRequest $form */
        $form = $request->getAttribute(AdminOrganizationUpdateRequest::class);

        try {
            $this->organizationApiFacade->updateOrganization(
                $organizationId,
                $form->name,
                $form->slug,
                '1' === $form->anonymousAccess,
            );
        } catch (OrganizationExceptionInterface $exception) {
            $this->inertiaNotificationMessenger->flash($request, 'error', $exception->userMessage());

            return new RedirectResponse('/admin/organizations/' . $organizationId);
        }

        $this->auditApiFacade->record(
            event: 'organization.updated_by_admin',
            context: [
                'name'             => $form->name,
                'slug'             => $form->slug,
                'anonymous_access' => '1' === $form->anonymousAccess,
            ],
            organizationId: $organizationId,
            actorId: RequestContextAttributes::currentUserId($request) ?? '',
            subjectType: 'organization',
            subjectId: $organizationId,
        );

        $this->inertiaNotificationMessenger->flash($request, 'success', 'Organization updated.');

        return new RedirectResponse('/admin/organizations/' . $organizationId);
    }
}
