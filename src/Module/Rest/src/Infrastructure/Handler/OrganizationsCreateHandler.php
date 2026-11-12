<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Organization\Api\Exception\OrganizationExceptionInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Rest\Attribute\ApiAudited;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Request\CreateOrganizationRequest;
use Packio\Rest\Infrastructure\Resource\OrganizationResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/api/v1/organizations', name: 'api.v1.organizations.create')]
#[ApiAuthenticated]
#[ApiScope('organizations:write')]
#[RateLimit(
    key: 'api_write',
    by: ['ip'],
    response: 'json',
    message: 'Too many API write requests. Please try again later.'
)]
#[MapRequest(source: CreateOrganizationRequest::class)]
#[ValidateMappedRequest(CreateOrganizationRequest::class)]
#[ApiAudited]
final readonly class OrganizationsCreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = RequestContextAttributes::currentUser($request);
        if (! $user instanceof UserDto) {
            return $this->apiErrorResponseFactory->unauthorized();
        }

        /** @var CreateOrganizationRequest $form */
        $form = $request->getAttribute(CreateOrganizationRequest::class);
        $name = $form->name();

        try {
            $organizationDto = $this->organizationApiFacade->registerOrganization($name, $user->id);
        } catch (OrganizationExceptionInterface $exception) {
            return $this->apiErrorResponseFactory->validation([
                'name' => [$exception->userMessage()],
            ]);
        }

        return $this->apiResponseFactory->json([
            'data' => OrganizationResource::fromDto($organizationDto),
        ], 201);
    }
}
