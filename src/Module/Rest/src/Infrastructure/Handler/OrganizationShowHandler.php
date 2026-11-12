<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\OrganizationResource;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/organizations/:organization', name: 'api.v1.organizations.show')]
#[ApiAuthenticated]
#[ApiScope('organizations:read')]
#[ApiOrganizationContext]
#[Can('organizations.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class OrganizationShowHandler implements RequestHandlerInterface
{
    public function __construct(private ApiResponseFactory $apiResponseFactory) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->apiResponseFactory->json([
            'data' => OrganizationResource::fromDto(RequestContextAttributes::requireCurrentOrganization($request)),
        ]);
    }
}
