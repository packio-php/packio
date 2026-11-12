<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use const FILTER_VALIDATE_BOOL;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\ReleaseResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function filter_var;

#[Get('/api/v1/organizations/:organization/packages/:vendor/:package/releases', name: 'api.v1.organizations.packages.releases.index')]
#[ApiAuthenticated]
#[ApiScope('releases:read')]
#[ApiOrganizationContext]
#[Can('packages.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class ReleasesIndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private PackageApiFacadeInterface $packageApiFacade,
        private ReleaseApiFacadeInterface $releaseApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $packageName     = ApiRouteResourceResolver::packageName($request);
        $package         = $this->packageApiFacade->findPackageInOrganization($organizationDto->id, $packageName);
        if (! $package instanceof PackageDto) {
            return $this->apiErrorResponseFactory->notFound('Package not found.');
        }

        $includeInactive = filter_var($request->getQueryParams()['include_inactive'] ?? false, FILTER_VALIDATE_BOOL);
        $releases        = [];
        foreach ($this->releaseApiFacade->releasesForPackage($packageName, $includeInactive, $organizationDto->id) as $releaseDto) {
            $releases[] = ReleaseResource::fromDto($releaseDto);
        }

        return $this->apiResponseFactory->json([
            'data' => $releases,
        ]);
    }
}
