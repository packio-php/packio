<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Infrastructure\Response\SourceProviderInstancePresenter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function array_map;
use function is_string;

#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get(
    '/organization/:organization/source-provider-instances',
    name: 'sources.provider-instances.index',
)]
final readonly class SourceProviderInstancesHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourceProviderInstancePresenter $sourceProviderInstancePresenter,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organization = RequestContextAttributes::currentOrganization($request);
        if (! $organization instanceof OrganizationDto) {
            return new JsonResponse([
                'error' => 'Organization not found.',
            ], 404);
        }

        $params       = $request->getQueryParams();
        $providerType = is_string($params['provider_type'] ?? null) && '' !== $params['provider_type']
            ? $params['provider_type']
            : null;

        return new JsonResponse([
            'instances' => array_map(
                $this->sourceProviderInstancePresenter->present(...),
                $this->sourceApiFacade->sourceProviderInstances($organization->id, $providerType),
            ),
        ]);
    }
}
