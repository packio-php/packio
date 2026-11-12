<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Release\Api\Dto\ReleaseDto;
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

use function is_string;
use function trim;

#[Get('/api/v1/organizations/:organization/packages/:vendor/:package/releases/by-version', name: 'api.v1.organizations.packages.releases.by-version')]
#[ApiAuthenticated]
#[ApiScope('releases:read')]
#[ApiOrganizationContext]
#[Can('packages.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class ReleaseShowByVersionHandler implements RequestHandlerInterface
{
    public function __construct(
        private ReleaseApiFacadeInterface $releaseApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $version = $request->getQueryParams()['version'] ?? null;
        $version = is_string($version) ? trim($version) : '';
        if ('' === $version) {
            return $this->apiErrorResponseFactory->validation([
                'version' => ['Version is required.'],
            ]);
        }

        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $release         = $this->releaseApiFacade->findRelease(ApiRouteResourceResolver::packageName($request), $version, $organizationDto->id);
        if (! $release instanceof ReleaseDto) {
            return $this->apiErrorResponseFactory->notFound('Release not found.');
        }

        return $this->apiResponseFactory->json([
            'data' => ReleaseResource::fromDto($release),
        ]);
    }
}
