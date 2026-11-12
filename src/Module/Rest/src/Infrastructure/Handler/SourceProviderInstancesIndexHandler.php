<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Common\Logging\SafeExceptionContext;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\SourceProviderInstanceResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Source\Api\Exception\SourceProviderInstanceError;
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

#[Get('/api/v1/organizations/:organization/source-provider-instances', name: 'api.v1.organizations.source-provider-instances.index')]
#[ApiAuthenticated]
#[ApiScope('sources:read')]
#[ApiOrganizationContext]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class SourceProviderInstancesIndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
        private ?LoggerInterface $logger = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $providerType    = $request->getQueryParams()['provider_type'] ?? null;
        $providerType    = is_string($providerType) && '' !== trim($providerType) ? trim($providerType) : null;
        $instances       = [];

        try {
            foreach ($this->sourceApiFacade->sourceProviderInstances($organizationDto->id, $providerType) as $sourceProviderInstanceDto) {
                $instances[] = SourceProviderInstanceResource::fromDto($sourceProviderInstanceDto);
            }
        } catch (Throwable $exception) {
            $this->logger?->error('Source provider instance listing failed.', SafeExceptionContext::forRequest($exception, $request));
            $error = SourceProviderInstanceError::fromException($exception);

            return $this->apiErrorResponseFactory->error($error->code, $error->message, $error->status);
        }

        return $this->apiResponseFactory->json([
            'data' => $instances,
        ]);
    }
}
