<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Identity\Api\Dto\IssuedRegistryAccessTokenDto;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\RegistryTokenResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Domain\Organization\ActorContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Put;

#[Put('/api/v1/organizations/:organization/registry-tokens/:tokenId/regenerate', name: 'api.v1.organizations.registry-tokens.regenerate')]
#[ApiAuthenticated]
#[ApiScope('tokens:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_token_create', by: ['ip', 'user'], response: 'json', message: 'Too many API token requests. Please try again later.')]
#[Can('tokens.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class RegistryTokensRegenerateHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorContext = RequestContextAttributes::currentOrganizationActorContext($request);
        if (! $actorContext instanceof ActorContext) {
            return $this->apiErrorResponseFactory->forbidden();
        }

        $tokenId = ApiRouteResourceResolver::tokenId($request);
        if ('' === $tokenId) {
            return $this->apiErrorResponseFactory->notFound('Registry token not found.');
        }

        $registryAccessTokenRegenerationDto = $this->identityApiFacade->regenerateRegistryAccessTokenInContext($actorContext, $tokenId);
        if ($registryAccessTokenRegenerationDto->isPersistenceFailed()) {
            return $this->apiErrorResponseFactory->error(
                'registry_token_regenerate_failed',
                'Registry token could not be regenerated.',
                409,
            );
        }

        if (! $registryAccessTokenRegenerationDto->success || ! $registryAccessTokenRegenerationDto->issued instanceof IssuedRegistryAccessTokenDto || null === $registryAccessTokenRegenerationDto->revokedId) {
            return $this->apiErrorResponseFactory->notFound('Registry token not found.');
        }

        return $this->apiResponseFactory->json([
            'data'        => RegistryTokenResource::fromIssued($registryAccessTokenRegenerationDto->issued),
            'plain_token' => $registryAccessTokenRegenerationDto->issued->plainTextToken,
            'revoked_id'  => $registryAccessTokenRegenerationDto->revokedId,
        ], 201);
    }
}
