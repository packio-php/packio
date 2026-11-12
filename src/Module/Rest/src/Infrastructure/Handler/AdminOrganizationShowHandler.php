<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\OrganizationInvitationResource;
use Packio\Rest\Infrastructure\Resource\OrganizationMemberResource;
use Packio\Rest\Infrastructure\Resource\OrganizationResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/v1/admin/organizations/:organizationId', name: 'api.v1.admin.organizations.show')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[Can('admin.access')]
final readonly class AdminOrganizationShowHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organization = $this->organization($request);
        if (! $organization instanceof OrganizationDto) {
            return $this->apiErrorResponseFactory->notFound('Organization not found.');
        }

        $members = [];
        foreach ($this->organizationApiFacade->members($organization->id) as $organizationMemberDto) {
            $members[] = OrganizationMemberResource::fromDto($organizationMemberDto);
        }

        $invitations = [];
        foreach ($this->organizationApiFacade->invitations($organization->id) as $organizationInvitationDto) {
            $invitations[] = OrganizationInvitationResource::fromDto($organizationInvitationDto, includeToken: false);
        }

        return $this->apiResponseFactory->json([
            'data'        => OrganizationResource::fromDto($organization),
            'members'     => $members,
            'invitations' => $invitations,
        ]);
    }

    private function organization(ServerRequestInterface $serverRequest): ?OrganizationDto
    {
        return ApiRouteResourceResolver::organization($serverRequest, $this->organizationApiFacade);
    }
}
