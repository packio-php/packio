<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Admin\Infrastructure\Request\AdminOrganizationDeleteRequest;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Organization\Api\Dto\OrganizationDeletionContext;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationOutcome;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

use function in_array;
use function is_string;

#[Post('/admin/organizations/:organizationId/delete', name: 'admin.organizations.delete')]
#[Authenticated]
#[Can('admin.access')]
#[MapRequest(body: AdminOrganizationDeleteRequest::class)]
#[ValidateMappedRequest(AdminOrganizationDeleteRequest::class)]
#[ValidationFailed(
    redirectTo: '/admin/organizations/{organizationId}',
    message: 'Invalid request data.',
    notify: ValidationNotify::Flash,
)]
final readonly class AdminOrganizationDeleteHandler implements RequestHandlerInterface
{
    private const string ACTION_ORIGIN = 'admin-organization-delete';

    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private LoggerInterface $logger,
        private InertiaNotificationMessenger $inertiaNotificationMessenger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = RouteAttributes::uuid($request, 'organizationId');

        if ('' === $organizationId) {
            $rawId = $request->getAttribute('organizationId');
            $this->reject($request, is_string($rawId) ? $rawId : '', 'Organization not found.');

            return new RedirectResponse('/admin/organizations');
        }

        $adminUserId = RequestContextAttributes::currentUserId($request);
        if (null === $adminUserId) {
            $this->reject($request, $organizationId, 'Organization not found.');

            return new RedirectResponse('/admin/organizations');
        }

        $organization = $this->organizationApiFacade->findOrganization($organizationId);
        if (! $organization instanceof OrganizationDto) {
            $this->reject($request, $organizationId, 'Organization not found.');

            return new RedirectResponse('/admin/organizations');
        }

        /** @var AdminOrganizationDeleteRequest $form */
        $form = $request->getAttribute(AdminOrganizationDeleteRequest::class);
        // Business confirmation depends on the resolved organization, not only on the mapped DTO.
        if ($form->confirmationName !== $organization->name) {
            $this->reject($request, $organizationId, 'Type the exact organization name to confirm deletion.');

            return new RedirectResponse('/admin/organizations/' . $organizationId);
        }

        $organizationDeletionInitiationResult = $this->organizationApiFacade->initiateOrganizationDeletion(new OrganizationDeletionContext(
            $organizationId,
            $adminUserId,
            origin: 'admin',
            auditEvent: 'organization.delete.initiated_by_admin',
        ));

        if (OrganizationDeletionInitiationOutcome::NotFound === $organizationDeletionInitiationResult->outcome) {
            $this->reject($request, $organizationId, 'Organization not found.');

            return new RedirectResponse('/admin/organizations');
        }

        if (OrganizationDeletionInitiationOutcome::PublicationFailed === $organizationDeletionInitiationResult->outcome) {
            throw new RuntimeException('Organization deletion cleanup job could not be published after audit recording.');
        }

        if (! in_array($organizationDeletionInitiationResult->outcome, [
            OrganizationDeletionInitiationOutcome::Initiated,
            OrganizationDeletionInitiationOutcome::AlreadyInProgress,
        ], true)) {
            $this->reject($request, $organizationId, 'Organization is already deleting.');

            return new RedirectResponse('/admin/organizations/' . $organizationId);
        }

        $this->inertiaNotificationMessenger->flash(
            $request,
            'success',
            'Organization deletion queued.',
        );

        return new RedirectResponse('/admin/organizations');
    }

    private function reject(ServerRequestInterface $serverRequest, string $organizationId, string $message): void
    {
        $this->logger->warning('Admin organization deletion request was rejected.', [
            'admin_user_id'   => RequestContextAttributes::currentUserId($serverRequest) ?? '',
            'organization_id' => $organizationId,
            'outcome'         => 'rejected',
            'origin'          => self::ACTION_ORIGIN,
        ]);

        $this->inertiaNotificationMessenger->flash($serverRequest, 'error', $message);
    }
}
