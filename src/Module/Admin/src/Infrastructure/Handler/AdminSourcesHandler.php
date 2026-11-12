<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Packio\Admin\Infrastructure\Page\AdminSourcesPageDataBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/admin/sources', name: 'admin.sources')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminSourcesHandler implements RequestHandlerInterface
{
    public function __construct(private AdminSourcesPageDataBuilder $adminSourcesPageDataBuilder) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        return $inertia->render('Admin/Sources', $this->adminSourcesPageDataBuilder->build($request));
    }
}
