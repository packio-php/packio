<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Packio\Rest\Application\Security\ApiScopeEvaluator;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Http\ApiRequestAttributes;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\SourceResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/organizations/:organization/sources/:sourceId', name: 'api.v1.organizations.sources.show')]
#[ApiAuthenticated]
#[ApiScope('sources:read')]
#[ApiOrganizationContext]
#[Can('sources.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class SourceShowHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
        private OrganizationPolicy $organizationPolicy,
        private ApiScopeEvaluator $apiScopeEvaluator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $source = ApiRouteResourceResolver::source($request, $this->sourceApiFacade);
        if (! $source instanceof SourceDto) {
            return $this->apiErrorResponseFactory->notFound('Source not found.');
        }

        $sourceResource = SourceResource::fromDto($source);
        // The webhook secret lets its holder forge push webhooks, so only managers with a write-scoped token may see it.
        $canSeeWebhookSecret = $this->organizationPolicy->canManage(
            RequestContextAttributes::requireCurrentOrganization($request),
            RequestContextAttributes::currentMembership($request),
        ) && ApiRequestAttributes::tokenAllows($request, $this->apiScopeEvaluator, 'sources:write');

        return $this->apiResponseFactory->json([
            'data' => $canSeeWebhookSecret ? $sourceResource : SourceResource::withoutWebhookSecret($sourceResource),
        ]);
    }
}
