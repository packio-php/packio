<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Registry\Infrastructure\Page\RegistrySetupPageDataBuilder;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Authenticated]
#[Can('organizations.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get(
    '/organization/:organization/registry/setup',
    name: 'registry.setup',
    middleware: [RequireExistingOrganizationMiddleware::class]
)]
final readonly class RegistrySetupHandler implements RequestHandlerInterface
{
    public function __construct(private RegistrySetupPageDataBuilder $registrySetupPageDataBuilder) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var InertiaInterface $inertia */
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);

        $currentUser = RequestContextAttributes::currentUser($request);

        return $inertia->render(
            'Registry/Setup',
            $this->registrySetupPageDataBuilder->build(
                $organizationDto,
                $currentUser instanceof UserDto ? $currentUser : null,
            ),
        );
    }
}
