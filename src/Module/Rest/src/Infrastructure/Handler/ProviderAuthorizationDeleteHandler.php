<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Request\DeleteProviderAuthorizationRequest;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Delete;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Delete('/api/v1/organizations/:organization/provider-authorizations/:authorizationId', name: 'api.v1.organizations.provider-authorizations.delete')]
#[ApiAuthenticated]
#[ApiScope('sources:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_destructive', by: ['ip', 'user'], response: 'json', message: 'Too many destructive API requests. Please try again later.')]
#[MapRequest(source: DeleteProviderAuthorizationRequest::class)]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class ProviderAuthorizationDeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorContext = RequestContextAttributes::currentOrganizationActorContext($request);
        if (! $actorContext instanceof ActorContext) {
            return $this->apiErrorResponseFactory->forbidden();
        }

        $authorizationId = ApiRouteResourceResolver::providerAuthorizationId($request);
        if ('' === $authorizationId) {
            return $this->apiErrorResponseFactory->notFound('Provider authorization not found.');
        }

        /** @var DeleteProviderAuthorizationRequest $form */
        $form               = $request->getAttribute(DeleteProviderAuthorizationRequest::class);
        $newAuthorizationId = $form->newAuthorizationId();

        $result = $this->sourceApiFacade->reassignAndDeleteProviderAuthorizationInContext(
            $actorContext,
            $authorizationId,
            $newAuthorizationId,
        );
        if (! $result['success']) {
            $error = $result['error'] ?? 'Provider authorization could not be deleted.';
            if (true === ($result['not_found'] ?? false)) {
                return $this->apiErrorResponseFactory->notFound('Provider authorization not found.');
            }

            return $this->apiErrorResponseFactory->error(
                'provider_authorization_delete_failed',
                $error,
                409,
            );
        }

        return $this->apiResponseFactory->json([
            'data' => [
                'deleted'    => true,
                'reassigned' => $result['reassigned'] ?? 0,
            ],
        ]);
    }
}
