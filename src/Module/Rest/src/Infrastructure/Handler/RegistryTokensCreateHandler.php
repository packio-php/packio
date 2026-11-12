<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Request\CreateRegistryTokenRequest;
use Packio\Rest\Infrastructure\Resource\RegistryTokenResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Domain\Organization\ActorContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/api/v1/organizations/:organization/registry-tokens', name: 'api.v1.organizations.registry-tokens.create')]
#[ApiAuthenticated]
#[ApiScope('tokens:write')]
#[ApiOrganizationContext]
#[RateLimit(
    key: 'api_token_create',
    by: ['ip'],
    response: 'json',
    message: 'Too many API token requests. Please try again later.'
)]
#[MapRequest(source: CreateRegistryTokenRequest::class)]
#[ValidateMappedRequest(CreateRegistryTokenRequest::class)]
#[Can('tokens.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class RegistryTokensCreateHandler implements RequestHandlerInterface
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

        /** @var CreateRegistryTokenRequest $form */
        $form = $request->getAttribute(CreateRegistryTokenRequest::class);
        $name = $form->name();

        $issuedRegistryAccessTokenDto = $this->identityApiFacade->issueRegistryAccessTokenInContext(
            $actorContext,
            $name,
            $form->permissions(),
        );

        return $this->apiResponseFactory->json([
            'data'        => RegistryTokenResource::fromIssued($issuedRegistryAccessTokenDto),
            'plain_token' => $issuedRegistryAccessTokenDto->plainTextToken,
        ], 201);
    }
}
