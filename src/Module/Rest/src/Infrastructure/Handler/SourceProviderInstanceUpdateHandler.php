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
use Packio\Rest\Infrastructure\Request\UpdateSourceProviderInstanceRequest;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\SourceProviderInstanceResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceProviderInstanceRequestDto;
use Packio\Source\Api\Exception\SourceProviderInstanceError;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Patch;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Throwable;

#[Patch('/api/v1/organizations/:organization/source-provider-instances/:instanceId', name: 'api.v1.organizations.source-provider-instances.update')]
#[ApiAuthenticated]
#[ApiScope('sources:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_write', by: ['ip', 'user'], response: 'json', message: 'Too many API write requests. Please try again later.')]
#[MapRequest(source: UpdateSourceProviderInstanceRequest::class)]
#[ValidateMappedRequest(UpdateSourceProviderInstanceRequest::class)]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class SourceProviderInstanceUpdateHandler implements RequestHandlerInterface
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

        $instanceId = ApiRouteResourceResolver::sourceProviderInstanceId($request);
        if ('' === $instanceId) {
            return $this->apiErrorResponseFactory->error('source_provider_instance_not_found', 'Source provider instance was not found.', 404);
        }

        /** @var UpdateSourceProviderInstanceRequest $form */
        $form = $request->getAttribute(UpdateSourceProviderInstanceRequest::class);

        /** @var SourceProviderInstanceRequestDto $dto Validated by ValidateMappedRequest. */
        $dto  = $form->toDto();

        try {
            $instance = $this->sourceApiFacade->updateSourceProviderInstanceInContext($actorContext, $instanceId, $dto);
        } catch (Throwable $exception) {
            return $this->exceptionResponse($exception, $request);
        }

        return $this->apiResponseFactory->json([
            'data' => SourceProviderInstanceResource::fromDto($instance),
        ]);
    }

    private function exceptionResponse(Throwable $throwable, ServerRequestInterface $serverRequest): ResponseInterface
    {
        $sourceProviderInstanceError = SourceProviderInstanceError::fromException($throwable);
        $this->logger?->error('Source provider instance operation failed.', SafeExceptionContext::forRequest($throwable, $serverRequest));

        return $this->apiErrorResponseFactory->error($sourceProviderInstanceError->code, $sourceProviderInstanceError->message, $sourceProviderInstanceError->status);
    }
}
