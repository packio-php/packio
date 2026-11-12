<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Organization\Api\Dto\OrganizationDeletionContext;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationOutcome;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Rest\Attribute\ApiAudited;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Delete;

#[Delete('/api/v1/admin/organizations/:organizationId', name: 'api.v1.admin.organizations.delete')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[ApiAudited]
#[RateLimit(key: 'api_admin_write', by: ['ip', 'user'], response: 'json', message: 'Too many admin API write requests. Please try again later.')]
#[Can('admin.access')]
final readonly class AdminOrganizationDeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = ApiRouteResourceResolver::organizationId($request);
        if ('' === $organizationId) {
            return $this->apiErrorResponseFactory->notFound('Organization not found.');
        }

        $organizationDeletionInitiationResult = $this->organizationApiFacade->initiateOrganizationDeletion(new OrganizationDeletionContext(
            $organizationId,
            RequestContextAttributes::currentUserId($request) ?? '',
            origin: 'api',
        ));

        return match ($organizationDeletionInitiationResult->outcome) {
            OrganizationDeletionInitiationOutcome::Initiated,
            OrganizationDeletionInitiationOutcome::AlreadyInProgress => $this->apiResponseFactory->empty(),
            default                                                  => $this->apiErrorResponseFactory->notFound('Organization not found.'),
        };
    }
}
