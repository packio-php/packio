<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Registry\Api\RegistryApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/organizations/:organization/registry/overview', name: 'api.v1.organizations.registry.overview')]
#[ApiAuthenticated]
#[ApiScope('packages:read')]
#[ApiOrganizationContext]
#[Can('packages.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class RegistryOverviewHandler implements RequestHandlerInterface
{
    public function __construct(
        private RegistryApiFacadeInterface $registryApiFacade,
        private ApiResponseFactory $apiResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);

        return $this->apiResponseFactory->json([
            'data' => $this->registryApiFacade->packageOverview($organizationDto->id),
        ]);
    }
}
