<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Packio\Admin\Infrastructure\Page\AdminDashboardPageDataBuilder;
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
        private AdminDashboardPageDataBuilder $adminDashboardPageDataBuilder,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        return $inertia->render('Admin/Dashboard', $this->adminDashboardPageDataBuilder->build());
    }
}
