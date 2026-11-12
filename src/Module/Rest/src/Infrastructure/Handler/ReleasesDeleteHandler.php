<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
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
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Delete;

#[Delete('/api/v1/organizations/:organization/packages/:vendor/:package/releases', name: 'api.v1.organizations.packages.releases.delete')]
#[ApiAuthenticated]
#[ApiScope('releases:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_destructive', by: ['ip', 'user'], response: 'json', message: 'Too many destructive API requests. Please try again later.')]
#[Can('packages.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class ReleasesDeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private ReleaseApiFacadeInterface $releaseApiFacade,
        private ArchiveStorageCleanupApiFacadeInterface $archiveStorageCleanupApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorContext = RequestContextAttributes::currentOrganizationActorContext($request);
        if (! $actorContext instanceof ActorContext) {
            return $this->apiErrorResponseFactory->forbidden();
        }

        $packageName = ApiRouteResourceResolver::packageName($request);

        // Scheduling must complete before the release metadata needed to discover archives is removed.
        $archiveCleanup = $this->archiveStorageCleanupApiFacade->schedulePackageArchiveCleanup(
            $actorContext->organizationId,
            $packageName,
            null,
            'api',
        );
        if (0 < $archiveCleanup['failed']) {
            return $this->apiErrorResponseFactory->error('service_unavailable', 'Archive cleanup could not be fully scheduled.', 503);
        }

        $deleted = $this->releaseApiFacade->deleteReleasesForPackageInContext($actorContext, $packageName);

        return $this->apiResponseFactory->json([
            'data' => [
                'deleted' => $deleted,
            ],
        ]);
    }
}
