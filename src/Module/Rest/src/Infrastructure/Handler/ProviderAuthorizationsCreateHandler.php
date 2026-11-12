<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Common\Logging\SafeExceptionContext;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Request\CreateProviderAuthorizationRequest;
use Packio\Rest\Infrastructure\Resource\ProviderAuthorizationResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\ProviderAuthorizationRequestDto;
use Packio\Source\Api\Exception\SourceInputException;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Throwable;

#[Post('/api/v1/organizations/:organization/provider-authorizations', name: 'api.v1.organizations.provider-authorizations.create')]
#[ApiAuthenticated]
#[ApiScope('sources:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_write', by: ['ip', 'user'], response: 'json', message: 'Too many API write requests. Please try again later.')]
#[MapRequest(source: CreateProviderAuthorizationRequest::class)]
#[ValidateMappedRequest(CreateProviderAuthorizationRequest::class)]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class ProviderAuthorizationsCreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
        private ?LoggerInterface $logger = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorContext = RequestContextAttributes::currentOrganizationActorContext($request);
        if (! $actorContext instanceof ActorContext) {
            return $this->apiErrorResponseFactory->forbidden();
        }

        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);

        /** @var CreateProviderAuthorizationRequest $form */
        $form     = $request->getAttribute(CreateProviderAuthorizationRequest::class);
        $provider = $form->provider();

        /** @var array<string, mixed> $authorizationPayload Validated by ValidateMappedRequest. */
        $authorizationPayload = $form->authorizationPayload();

        try {
            $authorization = $this->sourceApiFacade->authorizeProviderInContext(
                $actorContext,
                new ProviderAuthorizationRequestDto(
                    provider: $provider,
                    organizationId: $organizationDto->id,
                    authStrategy: $form->authStrategy(),
                    payload: $authorizationPayload,
                ),
            );
        } catch (SourceInputException $exception) {
            // This type contains only explicit, fixed public validation messages.
            return $this->apiErrorResponseFactory->validation([
                'provider_authorization' => [$exception->getMessage()],
            ]);
        } catch (Throwable $exception) {
            $this->logger?->error('API operation failed.', SafeExceptionContext::forRequest($exception, $request));

            return $this->apiErrorResponseFactory->error('internal_error', 'Unable to complete the operation.', 500);
        }

        return $this->apiResponseFactory->json([
            'data' => ProviderAuthorizationResource::fromDto($authorization),
        ], 201);
    }
}
