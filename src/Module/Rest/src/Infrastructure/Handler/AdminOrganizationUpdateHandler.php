<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Organization\Api\Exception\OrganizationExceptionInterface;
use Packio\Organization\Api\Exception\OrganizationNameException;
use Packio\Organization\Api\Exception\OrganizationNotFoundException;
use Packio\Organization\Api\Exception\OrganizationSlugException;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Rest\Attribute\ApiAudited;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Request\UpdateAdminOrganizationRequest;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\OrganizationResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Patch;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Patch('/api/v1/admin/organizations/:organizationId', name: 'api.v1.admin.organizations.update')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[RateLimit(key: 'api_admin_write', by: ['ip', 'user'], response: 'json', message: 'Too many admin API write requests. Please try again later.')]
#[MapRequest(source: UpdateAdminOrganizationRequest::class)]
#[ValidateMappedRequest(UpdateAdminOrganizationRequest::class)]
#[ApiAudited]
#[Can('admin.access')]
final readonly class AdminOrganizationUpdateHandler implements RequestHandlerInterface
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

        /** @var UpdateAdminOrganizationRequest $form */
        $form = $request->getAttribute(UpdateAdminOrganizationRequest::class);

        $name = $form->name($organization->name);

        $slug = $form->slug($organization->slug);

        /** @var bool $anonymousAccess Validated by ValidateMappedRequest. */
        $anonymousAccess = $form->anonymousAccess($organization->anonymousAccess);

        try {
            $organizationDto = $this->organizationApiFacade->updateOrganization(
                $organization->id,
                $name,
                $slug,
                $anonymousAccess,
            );
        } catch (OrganizationExceptionInterface $exception) {
            if ($exception instanceof OrganizationNotFoundException) {
                return $this->apiErrorResponseFactory->notFound('Organization not found.');
            }

            if ($exception instanceof OrganizationSlugException) {
                return $this->apiErrorResponseFactory->validation([
                    'slug' => [$exception->userMessage()],
                ]);
            }

            if ($exception instanceof OrganizationNameException) {
                return $this->apiErrorResponseFactory->validation([
                    'name' => [$exception->userMessage()],
                ]);
            }

            return $this->apiErrorResponseFactory->validation([
                'organization' => [$exception->userMessage()],
            ]);
        }

        return $this->apiResponseFactory->json([
            'data' => OrganizationResource::fromDto($organizationDto),
        ]);
    }

    private function organization(ServerRequestInterface $serverRequest): ?OrganizationDto
    {
        return ApiRouteResourceResolver::organization($serverRequest, $this->organizationApiFacade);
    }
}
