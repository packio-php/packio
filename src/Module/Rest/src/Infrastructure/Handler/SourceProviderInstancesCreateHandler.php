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
use Packio\Rest\Infrastructure\Request\CreateSourceProviderInstanceRequest;
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
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Throwable;

#[Post('/api/v1/organizations/:organization/source-provider-instances', name: 'api.v1.organizations.source-provider-instances.create')]
#[ApiAuthenticated]
#[ApiScope('sources:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_write', by: ['ip', 'user'], response: 'json', message: 'Too many API write requests. Please try again later.')]
#[MapRequest(source: CreateSourceProviderInstanceRequest::class)]
#[ValidateMappedRequest(CreateSourceProviderInstanceRequest::class)]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class SourceProviderInstancesCreateHandler implements RequestHandlerInterface
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

        /** @var CreateSourceProviderInstanceRequest $form */
        $form       = $request->getAttribute(CreateSourceProviderInstanceRequest::class);

        /** @var SourceProviderInstanceRequestDto $requestDto Validated by ValidateMappedRequest. */
        $requestDto = $form->toDto();

        try {
            $instance = $this->sourceApiFacade->createSourceProviderInstanceInContext($actorContext, $requestDto);
        } catch (Throwable $exception) {
            return $this->exceptionResponse($exception, $request);
        }

        return $this->apiResponseFactory->json([
            'data' => SourceProviderInstanceResource::fromDto($instance),
        ], 201);
    }

    private function exceptionResponse(Throwable $throwable, ServerRequestInterface $serverRequest): ResponseInterface
    {
        $sourceProviderInstanceError = SourceProviderInstanceError::fromException($throwable);
        $this->logger?->error('Source provider instance operation failed.', SafeExceptionContext::forRequest($throwable, $serverRequest));

        return $this->apiErrorResponseFactory->error($sourceProviderInstanceError->code, $sourceProviderInstanceError->message, $sourceProviderInstanceError->status);
    }
}
