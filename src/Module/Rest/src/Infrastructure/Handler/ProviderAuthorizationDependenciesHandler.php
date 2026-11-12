<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/organizations/:organization/provider-authorizations/:authorizationId/dependencies', name: 'api.v1.organizations.provider-authorizations.dependencies')]
#[ApiAuthenticated]
#[ApiScope('sources:read')]
#[ApiOrganizationContext]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class ProviderAuthorizationDependenciesHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto    = RequestContextAttributes::requireCurrentOrganization($request);
        $authorizationId    = ApiRouteResourceResolver::providerAuthorizationId($request);
        if ('' === $authorizationId) {
            return $this->apiErrorResponseFactory->notFound('Provider authorization not found.');
        }

        return $this->apiResponseFactory->json([
            'data' => $this->sourceApiFacade->checkAuthorizationDependencies($authorizationId, $organizationDto->id),
        ]);
    }
}
