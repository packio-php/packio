<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Authenticated]
#[Can('packages.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get('/organization/:organization', name: 'dashboard.home', middleware: [RequireExistingOrganizationMiddleware::class])]
final readonly class HomeHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $org = RequestContextAttributes::currentOrganizationSlug($request) ?? '';

        return new RedirectResponse('/organization/' . $org . '/packages');
    }
}
