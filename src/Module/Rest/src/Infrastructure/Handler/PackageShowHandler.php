<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\PackageResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/organizations/:organization/packages/:vendor/:package', name: 'api.v1.organizations.packages.show')]
#[ApiAuthenticated]
#[ApiScope('packages:read')]
#[ApiOrganizationContext]
#[Can('packages.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class PackageShowHandler implements RequestHandlerInterface
{
    public function __construct(
        private PackageApiFacadeInterface $packageApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $packageName = ApiRouteResourceResolver::packageName($request);
        if ('' === $packageName) {
            return $this->apiErrorResponseFactory->notFound('Package not found.');
        }

        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $package         = $this->packageApiFacade->findPackageInOrganization($organizationDto->id, $packageName);
        if (! $package instanceof PackageDto) {
            return $this->apiErrorResponseFactory->notFound('Package not found.');
        }

        return $this->apiResponseFactory->json([
            'data' => PackageResource::fromDto($package),
        ]);
    }
}
