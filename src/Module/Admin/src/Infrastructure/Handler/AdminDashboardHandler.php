<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/admin', name: 'admin.dashboard')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminDashboardHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private PackageApiFacadeInterface $packageApiFacade,
        private SourceApiFacadeInterface $sourceApiFacade,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        return $inertia->render('Admin/Dashboard', [
            'stats' => [
                'users_count'         => $this->identityApiFacade->usersCount(),
                'admins_count'        => $this->identityApiFacade->adminsCount(),
                'organizations_count' => $this->organizationApiFacade->countOrganizations(),
                'packages_count'      => $this->packageApiFacade->countPackages(),
                'sources_count'       => $this->sourceApiFacade->countSources(),
            ],
        ]);
    }
}
