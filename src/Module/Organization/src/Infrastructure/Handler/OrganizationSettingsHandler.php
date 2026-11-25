<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Handler;

use Common\Provider\SourceProviderMetadataCatalog;
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

use function array_filter;
use function array_map;
use function array_values;

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
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourceProviderMetadataCatalog $sourceProviderMetadataCatalog,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia         = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $selfHosted      = $this->selfHostedProviders();

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
            'source_provider_instances_enabled' => [] !== $selfHosted,
            'self_hosted_source_providers'      => $selfHosted,
        ]);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function selfHostedProviders(): array
    {
        $available = array_filter(
            $this->sourceApiFacade->availableProviders(),
            fn ($sourceProviderDto): bool => $this->sourceProviderMetadataCatalog->isSelfHosted($sourceProviderDto->key),
        );

        return array_values(array_map(
            fn ($sourceProviderDto): array => [
                'key'   => $sourceProviderDto->key,
                'label' => $this->sourceProviderMetadataCatalog->get($sourceProviderDto->key)->label ?? $sourceProviderDto->name,
            ],
            $available,
        ));
    }
}
