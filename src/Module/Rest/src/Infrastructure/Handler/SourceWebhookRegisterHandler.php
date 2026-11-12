<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\WebhookRegistrationResultResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Post('/api/v1/organizations/:organization/sources/:sourceId/webhook/register', name: 'api.v1.organizations.sources.webhook.register')]
#[ApiAuthenticated]
#[ApiScope('sources:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_queue_action', by: ['ip', 'user'], response: 'json', message: 'Too many API queue action requests. Please try again later.')]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class SourceWebhookRegisterHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $source = ApiRouteResourceResolver::source($request, $this->sourceApiFacade);
        if (! $source instanceof SourceDto) {
            return $this->apiErrorResponseFactory->notFound('Source not found.');
        }

        if (! $this->sourceApiFacade->supportsWebhookAutoRegistration($source->provider)) {
            return $this->apiErrorResponseFactory->error(
                'webhook_auto_registration_not_supported',
                'Automatic webhook registration is not supported for this provider.',
                409,
            );
        }

        $webhookRegistrationResultDto = $this->sourceApiFacade->tryRegisterWebhook($source->id);

        return $this->apiResponseFactory->json([
            'data' => WebhookRegistrationResultResource::fromDto($webhookRegistrationResultDto),
        ], $webhookRegistrationResultDto->success ? 200 : 409);
    }
}
