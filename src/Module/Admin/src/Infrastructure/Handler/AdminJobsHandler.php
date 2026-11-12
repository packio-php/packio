<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Packio\Admin\Infrastructure\Page\AdminJobPresenter;
use Packio\Admin\Infrastructure\Page\AdminOrganizationPresenter;
use Packio\Admin\Infrastructure\Request\AdminJobFilterQuery;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function array_map;
use function array_values;

#[Get('/admin/jobs', name: 'admin.jobs')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminJobsHandler implements RequestHandlerInterface
{
    public function __construct(
        private JobApiFacadeInterface $jobApiFacade,
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private AdminJobPresenter $adminJobPresenter,
        private AdminOrganizationPresenter $adminOrganizationPresenter,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia             = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        $adminJobFilterQuery = AdminJobFilterQuery::fromQueryParams($request->getQueryParams());

        $organizations      = $this->organizationIndex();
        $paginatedResultDto = $this->jobApiFacade->findPaginatedAll(
            $adminJobFilterQuery->paginationCriteria,
            $adminJobFilterQuery->jobFilterCriteria,
        );

        return $inertia->render('Admin/Jobs', [
            'jobs'               => array_map(
                fn (JobDto $jobDto): array => [
                    ...$this->adminJobPresenter->present($jobDto),
                    'organization'  => $organizations[$jobDto->organizationId] ?? null,
                    'deletionRunId' => $this->adminJobPresenter->deletionRunId($jobDto->payload),
                ],
                $paginatedResultDto->items,
            ),
            'pagination'         => $paginatedResultDto->toArray(),
            'filters'            => $adminJobFilterQuery->filters,
            'status_counts'      => $this->jobApiFacade->countAllByStatus($adminJobFilterQuery->jobFilterCriteria),
            'queue_progress'     => $this->jobApiFacade->queueProgress(),
            'auth_mail_progress' => $this->jobApiFacade->queueProgress('system:identity'),
            'job_types'          => $this->jobApiFacade->jobTypes(),
            'organizations'      => array_values($organizations),
        ]);
    }

    /**
     * @return array<string, array{id: string, slug: string, name: string, status: string}>
     */
    private function organizationIndex(): array
    {
        $organizations = [];
        foreach ($this->organizationApiFacade->allOrganizations() as $organizationDto) {
            $organizations[$organizationDto->id] = $this->adminOrganizationPresenter->present($organizationDto);
        }

        return $organizations;
    }
}
