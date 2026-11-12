<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ProviderAuthorizationResource;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function is_string;
use function trim;

#[Get('/api/v1/organizations/:organization/provider-authorizations', name: 'api.v1.organizations.provider-authorizations.index')]
#[ApiAuthenticated]
#[ApiScope('sources:read')]
#[ApiOrganizationContext]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class ProviderAuthorizationsIndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private ApiResponseFactory $apiResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $provider        = $request->getQueryParams()['provider'] ?? null;
        $provider        = is_string($provider) && '' !== trim($provider) ? trim($provider) : null;
        $items           = [];

        foreach ($this->sourceApiFacade->providerAuthorizations($organizationDto->id, $provider) as $providerAuthorizationDto) {
            $items[] = ProviderAuthorizationResource::fromDto($providerAuthorizationDto);
        }

        return $this->apiResponseFactory->json([
            'data' => $items,
        ]);
    }
}
