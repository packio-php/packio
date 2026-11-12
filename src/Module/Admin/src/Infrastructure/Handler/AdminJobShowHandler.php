<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Admin\Infrastructure\Http\AdminReturnUrl;
use Packio\Admin\Infrastructure\Page\AdminJobPresenter;
use Packio\Admin\Infrastructure\Page\AdminOrganizationPresenter;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\Dto\JobLogDto;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function array_map;

#[Get('/admin/jobs/:jobId', name: 'admin.jobs.show')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminJobShowHandler implements RequestHandlerInterface
{
    public function __construct(
        private JobApiFacadeInterface $jobApiFacade,
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private AdminJobPresenter $adminJobPresenter,
        private AdminOrganizationPresenter $adminOrganizationPresenter,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $jobId = RouteAttributes::uuid($request, 'jobId');
        if ('' === $jobId) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Job not found.');

            return new RedirectResponse('/admin/jobs');
        }

        $job = $this->jobApiFacade->findById($jobId);
        if (! $job instanceof JobDto) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Job not found.');

            return new RedirectResponse('/admin/jobs');
        }

        $inertia      = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        $organization = Uuid::isValid($job->organizationId)
            ? $this->organizationApiFacade->findOrganization($job->organizationId)
            : null;
        $logs         = $this->jobApiFacade->findLogsByJob($jobId);

        return $inertia->render('Admin/JobDetails', [
            'job'                 => [
                ...$this->adminJobPresenter->present($job),
                'errorDetails' => $job->errorDetails,
            ],
            'logs'                => array_map($this->logToArray(...), $logs),
            'organization_record' => $organization instanceof OrganizationDto ? $this->adminOrganizationPresenter->present($organization) : null,
            'deletion_run_id'     => $this->adminJobPresenter->deletionRunId($job->payload),
            'back_url'            => AdminReturnUrl::from($request, '/admin/jobs'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function logToArray(JobLogDto $jobLogDto): array
    {
        return [
            'id'        => $jobLogDto->id,
            'jobId'     => $jobLogDto->jobId,
            'level'     => $jobLogDto->level,
            'message'   => $jobLogDto->message,
            'context'   => $jobLogDto->context,
            'createdAt' => $jobLogDto->createdAt,
        ];
    }
}
