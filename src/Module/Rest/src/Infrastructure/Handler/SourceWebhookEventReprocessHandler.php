<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\WebhookResultResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

use function is_string;
use function trim;

#[Post('/api/v1/organizations/:organization/sources/:sourceId/webhook-events/:deliveryId/reprocess', name: 'api.v1.organizations.sources.webhook-events.reprocess')]
#[ApiAuthenticated]
#[ApiScope('webhooks:write')]
#[ApiOrganizationContext]
#[RateLimit(key: 'api_queue_action', by: ['ip', 'user'], response: 'json', message: 'Too many API queue action requests. Please try again later.')]
#[Can('webhooks.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class SourceWebhookEventReprocessHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private WebhookApiFacadeInterface $webhookApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $source = ApiRouteResourceResolver::source($request, $this->sourceApiFacade);
        if (! $source instanceof SourceDto) {
            return $this->apiErrorResponseFactory->notFound('Source not found.');
        }

        $deliveryId = $request->getAttribute('deliveryId');
        $deliveryId = is_string($deliveryId) ? trim($deliveryId) : '';
        if ('' === $deliveryId) {
            return $this->apiErrorResponseFactory->notFound('Webhook delivery not found.');
        }

        $webhookResultDto = $this->webhookApiFacade->reprocessDelivery($source->id, $deliveryId);
        if (404 === $webhookResultDto->statusCode) {
            return $this->apiErrorResponseFactory->notFound($webhookResultDto->message);
        }

        return $this->apiResponseFactory->json([
            'data' => WebhookResultResource::fromDto($webhookResultDto),
        ], $webhookResultDto->statusCode);
    }
}
