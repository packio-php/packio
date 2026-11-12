<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Common\Logging\SafeExceptionContext;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\Exception\PackageDeletionFailedException;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Domain\Organization\ActorContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Delete;

#[Delete('/api/v1/organizations/:organization/packages/:vendor/:package', name: 'api.v1.organizations.packages.delete')]
#[ApiAuthenticated]
#[ApiScope('packages:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_destructive', by: ['ip', 'user'], response: 'json', message: 'Too many destructive API requests. Please try again later.')]
#[Can('packages.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class PackageDeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private PackageApiFacadeInterface $packageApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
        private LoggerInterface $logger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorContext = RequestContextAttributes::currentOrganizationActorContext($request);
        if (! $actorContext instanceof ActorContext) {
            return $this->apiErrorResponseFactory->forbidden();
        }

        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $packageName     = ApiRouteResourceResolver::packageName($request);
        if ('' === $packageName || ! $this->packageApiFacade->findPackageInOrganization($organizationDto->id, $packageName) instanceof PackageDto) {
            return $this->apiErrorResponseFactory->notFound('Package not found.');
        }

        try {
            if (! $this->packageApiFacade->deletePackageInContext($actorContext, $packageName)) {
                return $this->apiErrorResponseFactory->notFound('Package not found.');
            }
        } catch (PackageDeletionFailedException $exception) {
            $this->logger->error('Package deletion failed.', SafeExceptionContext::forRequest($exception, $request));

            return $this->apiErrorResponseFactory->error('service_unavailable', 'Package deletion is temporarily unavailable. Retry the request.', 503);
        }

        return $this->apiResponseFactory->empty();
    }
}
