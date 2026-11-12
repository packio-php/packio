<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\OrganizationResource;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/admin/organizations', name: 'api.v1.admin.organizations.index')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[Can('admin.access')]
final readonly class AdminOrganizationsIndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private ApiResponseFactory $apiResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizations = [];
        foreach ($this->organizationApiFacade->allOrganizations() as $organizationDto) {
            $organizations[] = OrganizationResource::fromDto($organizationDto);
        }

        return $this->apiResponseFactory->json([
            'data' => $organizations,
        ]);
    }
}
