<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Common\Logging\SafeExceptionContext;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\ProviderAuthorizationResource;
use Packio\Rest\Infrastructure\Resource\ProviderResourceResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Source\Api\Dto\ProviderAuthorizationDto;
use Packio\Source\Api\Exception\ProviderError;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Throwable;

use function is_string;
use function trim;

#[Get('/api/v1/organizations/:organization/provider-authorizations/:authorizationId/resources', name: 'api.v1.organizations.provider-authorizations.resources')]
#[ApiAuthenticated]
#[ApiScope('sources:read')]
#[ApiOrganizationContext]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class ProviderAuthorizationResourcesHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
        private ?LoggerInterface $logger = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto    = RequestContextAttributes::requireCurrentOrganization($request);
        $authorizationId    = ApiRouteResourceResolver::providerAuthorizationId($request);
        $provider           = $request->getQueryParams()['provider'] ?? null;
        $provider           = is_string($provider) ? trim($provider) : '';

        if ('' === $authorizationId) {
            return $this->apiErrorResponseFactory->notFound('Provider authorization not found.');
        }

        if ('' === $provider) {
            return $this->apiErrorResponseFactory->validation([
                'provider' => ['Provider is required.'],
            ]);
        }

        try {
            $resources = $this->sourceApiFacade->providerResources($provider, $authorizationId, $organizationDto->id);
        } catch (Throwable $exception) {
            $this->logger?->error('Provider resource loading failed.', SafeExceptionContext::forRequest($exception, $request) + [
                'organization_id'  => $organizationDto->id,
                'authorization_id' => SafeExceptionContext::redact($authorizationId),
                'provider'         => SafeExceptionContext::redact($provider),
            ]);
            $error = ProviderError::fromException($exception);

            $authorizationPayload = null;
            if (401 === $error->status) {
                try {
                    $authorization = ApiRouteResourceResolver::providerAuthorization($request, $this->sourceApiFacade);
                    if ($authorization instanceof ProviderAuthorizationDto) {
                        $authorizationPayload                        = ProviderAuthorizationResource::fromDto($authorization);
                        $authorizationPayload['auth_failure_reason'] = $error->message;
                    }
                } catch (Throwable $cause) {
                    $this->logger?->error('Provider authorization lookup failed.', SafeExceptionContext::forRequest($cause, $request));
                }
            }

            return $this->apiErrorResponseFactory->error($error->code, $error->message, $error->status, 401 === $error->status ? [
                'authorization' => $authorizationPayload,
            ] : null);
        }

        $items = [];
        foreach ($resources as $resource) {
            $items[] = ProviderResourceResource::fromDto($resource);
        }

        return $this->apiResponseFactory->json([
            'data' => $items,
        ]);
    }
}
