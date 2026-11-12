<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Organization\Api\Exception\OrganizationExceptionInterface;
use Packio\Organization\Api\Exception\OrganizationNotFoundException;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Request\UpdateOrganizationRequest;
use Packio\Rest\Infrastructure\Resource\OrganizationResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Domain\Organization\ActorContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Patch;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Patch('/api/v1/organizations/:organization', name: 'api.v1.organizations.update')]
#[ApiAuthenticated]
#[ApiScope('organizations:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_write', by: ['ip', 'user'], response: 'json', message: 'Too many API write requests. Please try again later.')]
#[MapRequest(source: UpdateOrganizationRequest::class)]
#[ValidateMappedRequest(UpdateOrganizationRequest::class)]
#[Can('organizations.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class OrganizationUpdateHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorContext = RequestContextAttributes::currentOrganizationActorContext($request);
        if (! $actorContext instanceof ActorContext) {
            return $this->apiErrorResponseFactory->forbidden();
        }

        /** @var UpdateOrganizationRequest $form */
        $form            = $request->getAttribute(UpdateOrganizationRequest::class);
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);

        $name = $form->name($organizationDto->name);

        /** @var bool $anonymousAccess Validated by ValidateMappedRequest. */
        $anonymousAccess = $form->anonymousAccess($organizationDto->anonymousAccess);

        try {
            $organization = $this->organizationApiFacade->updateOrganizationSettingsInContext(
                $actorContext,
                $name,
                $anonymousAccess,
            );
        } catch (OrganizationExceptionInterface $exception) {
            if ($exception instanceof OrganizationNotFoundException) {
                return $this->apiErrorResponseFactory->notFound('Organization not found.');
            }

            return $this->apiErrorResponseFactory->validation([
                'name' => [$exception->userMessage()],
            ]);
        }

        return $this->apiResponseFactory->json([
            'data' => OrganizationResource::fromDto($organization),
        ]);
    }
}
