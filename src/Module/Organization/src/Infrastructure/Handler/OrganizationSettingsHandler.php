<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function array_any;

#[Authenticated]
#[Can('organizations.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get(
    '/organization/:organization/settings',
    name: 'organization.settings',
    middleware: [RequireExistingOrganizationMiddleware::class]
)]
final readonly class OrganizationSettingsHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private SourceApiFacadeInterface $sourceApiFacade
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia         = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);

        return $inertia->render('Organization/Settings', [
            'settings'                          => [
                'name'             => $organizationDto->name,
                'route_key'        => $organizationDto->slug,
                'anonymous_access' => $organizationDto->anonymousAccess,
                'active'           => $organizationDto->active,
                'created_at'       => $organizationDto->createdAt,
                'updated_at'       => $organizationDto->updatedAt,
            ],
            'members_count'                     => $this->organizationApiFacade->membersCount($organizationDto->id),
            'source_provider_instances_enabled' => $this->isSourceProviderEnabled(),
        ]);
    }

    private function isSourceProviderEnabled(): bool
    {
        return array_any($this->sourceApiFacade->availableProviders(), fn ($sourceProviderDto) => 'gitlab_self_hosted' === $sourceProviderDto->key);
    }
}
