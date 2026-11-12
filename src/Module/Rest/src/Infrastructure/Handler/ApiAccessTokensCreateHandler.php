<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Common\Exception\PublicValidationException;
use Common\Logging\SafeExceptionContext;
use OpenApi\Attributes as OA;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Resource\ApiAccessTokenResource;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Rest\Attribute\ApiAudited;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Request\CreateApiAccessTokenRequest;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Throwable;

use function str_starts_with;

#[Post('/api/v1/user/api-tokens', name: 'api.v1.user.api-tokens.create')]
#[ApiAuthenticated]
#[ApiScope('write')]
#[RateLimit(
    key: 'api_token_create',
    by: ['ip'],
    response: 'json',
    message: 'Too many API token requests. Please try again later.'
)]
#[MapRequest(source: CreateApiAccessTokenRequest::class)]
#[ValidateMappedRequest(CreateApiAccessTokenRequest::class)]
#[ApiAudited]
#[OA\Post(path: '/api/v1/user/api-tokens', operationId: 'createApiToken', requestBody: new OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        required: ['name'],
        properties: [
            new OA\Property(
                property: 'name',
                description: 'Human-readable token name.',
                type: 'string',
                example: 'CI automation',
                minLength: 1,
            ),
            new OA\Property(
                property: 'scopes',
                description: 'Requested scopes. The admin scope is only granted to users with the admin role.',
                type: 'array',
                items: new OA\Items(type: 'string', enum: ['read', 'write', 'admin']),
                example: ['read', 'write'],
            ),
            new OA\Property(
                property: 'expires_at',
                description: 'Optional expiration date in RFC3339 format.',
                type: 'string',
                format: 'date-time',
                example: '2026-12-31T23:59:59+00:00',
                nullable: true,
            ),
        ],
        type: 'object',
    ),
), tags: ['User API Tokens'], responses: [
    new OA\Response(
        response: 201,
        description: 'API token created',
        content: new OA\JsonContent(
            required: ['data', 'plain_token'],
            properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ApiAccessTokenResource'),
                new OA\Property(
                    property: 'plain_token',
                    description: 'Plain token value. It is returned once and is never stored in plaintext.',
                    type: 'string',
                    example: 'packio_api_example',
                ),
            ],
            type: 'object',
        ),
    ),
    new OA\Response(response: 401, description: 'Authentication required', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
    new OA\Response(response: 422, description: 'Invalid request', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
])]
final readonly class ApiAccessTokensCreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
        private ?LoggerInterface $logger = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = RequestContextAttributes::currentUser($request);
        if (! $user instanceof UserDto) {
            return $this->apiErrorResponseFactory->unauthorized();
        }

        /** @var CreateApiAccessTokenRequest $form */
        $form = $request->getAttribute(CreateApiAccessTokenRequest::class);
        $name = $form->name();

        try {
            $issued = $this->identityApiFacade->issueApiAccessTokenForUser(
                $user->id,
                $name,
                $form->scopes(),
                $form->expiresAt(),
            );
        } catch (PublicValidationException $exception) {
            // This type contains only explicit, fixed public validation messages.
            return $this->apiErrorResponseFactory->validation([
                (str_starts_with($exception->getMessage(), 'Unknown scope') ? 'scopes' : 'token') => [$exception->getMessage()],
            ]);
        } catch (Throwable $exception) {
            $this->logger?->error('API operation failed.', SafeExceptionContext::forRequest($exception, $request));

            return $this->apiErrorResponseFactory->error('internal_error', 'Unable to complete the operation.', 500);
        }

        return $this->apiResponseFactory->json([
            'data'        => ApiAccessTokenResource::fromDto($issued->token),
            'plain_token' => $issued->plainTextToken,
        ], 201);
    }
}
