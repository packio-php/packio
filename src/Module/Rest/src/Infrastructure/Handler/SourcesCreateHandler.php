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
use Packio\Rest\Infrastructure\Request\CreateSourceRequest;
use Packio\Rest\Infrastructure\Resource\SourceResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
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

#[Post('/api/v1/organizations/:organization/sources', name: 'api.v1.organizations.sources.create')]
#[ApiAuthenticated]
#[ApiScope('sources:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_write', by: ['ip', 'user'], response: 'json', message: 'Too many API write requests. Please try again later.')]
#[MapRequest(source: CreateSourceRequest::class)]
#[ValidateMappedRequest(CreateSourceRequest::class)]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class SourcesCreateHandler implements RequestHandlerInterface
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

        /** @var CreateSourceRequest $form */
        $form     = $request->getAttribute(CreateSourceRequest::class);
        $provider = $form->provider();
        $resource = $form->resource();

        /** @var string $visibility Validated by ValidateMappedRequest. */
        $visibility = $form->visibility();

        /** @var int $keepLastReleases Validated by ValidateMappedRequest. */
        $keepLastReleases = $form->keepLastReleases();

        /** @var array<string, mixed> $settings Validated by ValidateMappedRequest. */
        $settings = $form->settings();

        try {
            $source = $this->sourceApiFacade->registerSourceInContext(
                $actorContext,
                new SourceRegistrationRequestDto(
                    provider: $provider,
                    resource: $resource,
                    organizationId: $organizationDto->id,
                    defaultRef: $form->defaultRef(),
                    visibility: $visibility,
                    providerAuthorizationId: $form->providerAuthorizationId(),
                    settings: $settings,
                    keepLastReleases: $keepLastReleases,
                ),
            );
        } catch (SourceInputException $exception) {
            // This type contains only explicit, fixed public validation messages.
            return $this->apiErrorResponseFactory->validation([
                'source' => [$exception->getMessage()],
            ]);
        } catch (Throwable $exception) {
            $this->logger?->error('API operation failed.', SafeExceptionContext::forRequest($exception, $request));

            return $this->apiErrorResponseFactory->error('internal_error', 'Unable to complete the operation.', 500);
        }

        return $this->apiResponseFactory->json([
            'data' => SourceResource::fromDto($source),
        ], 201);
    }
}
