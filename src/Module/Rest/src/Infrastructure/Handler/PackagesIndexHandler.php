<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\PackageResource;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function is_string;
use function str_contains;
use function strtolower;
use function trim;

#[Get('/api/v1/organizations/:organization/packages', name: 'api.v1.organizations.packages.index')]
#[ApiAuthenticated]
#[ApiScope('packages:read')]
#[ApiOrganizationContext]
#[Can('packages.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class PackagesIndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private PackageApiFacadeInterface $packageApiFacade,
        private ApiResponseFactory $apiResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $search          = $request->getQueryParams()['search'] ?? null;
        $search          = is_string($search) ? strtolower(trim($search)) : '';
        $packages        = [];

        foreach ($this->packageApiFacade->packagesForOrganization($organizationDto->id) as $packageDto) {
            if ('' !== $search && ! str_contains(strtolower($packageDto->name . ' ' . $packageDto->description), $search)) {
                continue;
            }

            $packages[] = PackageResource::fromDto($packageDto);
        }

        return $this->apiResponseFactory->json([
            'data' => $packages,
        ]);
    }
}
