<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Admin\Infrastructure\Http\AdminReturnUrl;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

use function is_scalar;
use function rawurlencode;

#[Post('/admin/jobs/:jobId/restart', name: 'admin.jobs.restart')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminJobRestartHandler implements RequestHandlerInterface
{
    public function __construct(
        private JobApiFacadeInterface $jobApiFacade,
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $jobId = RouteAttributes::uuid($request, 'jobId');
        if ('' === $jobId) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Job not found.');

            return new RedirectResponse('/admin/jobs');
        }

        $adminUserId = RequestContextAttributes::currentUserId($request);
        if (null === $adminUserId) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Admin user not resolved.');

            return new RedirectResponse('/admin/jobs/' . $jobId);
        }

        $originalJob = $this->jobApiFacade->findById($jobId);
        if ($originalJob instanceof JobDto && 'organization.delete' === $originalJob->type) {
            $organizationId = $originalJob->payload['organization_id'] ?? null;
            $stage          = $originalJob->payload['stage'] ?? null;
            $newJobId       = is_scalar($organizationId) && is_scalar($stage)
                ? $this->organizationApiFacade->restartDeletionStage((string) $organizationId, (string) $stage, $adminUserId)
                : null;
        } elseif ($originalJob instanceof JobDto && 'source.delete' === $originalJob->type) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Use Source deletion recovery actions to restart this lifecycle job.');

            return new RedirectResponse('/admin/jobs/' . $jobId);
        } else {
            $newJobId = $this->jobApiFacade->restartAsAdmin($jobId, $adminUserId)?->id;
        }

        if (null === $newJobId) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Job is not restartable in its current lifecycle state.');

            return new RedirectResponse('/admin/jobs');
        }

        $this->inertiaNotificationMessenger->flash($request, 'success', 'Job restart queued.');

        $redirectUrl = '/admin/jobs/' . $newJobId;
        $backUrl     = AdminReturnUrl::from($request, '/admin/jobs');
        if ('/admin/jobs' !== $backUrl) {
            $redirectUrl .= '?return_to=' . rawurlencode($backUrl);
        }

        return new RedirectResponse($redirectUrl);
    }
}
