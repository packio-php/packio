<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Rest\Infrastructure\Resource\WebhookEventResource;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function is_numeric;
use function max;
use function min;

#[Get('/api/v1/organizations/:organization/sources/:sourceId/webhook-events', name: 'api.v1.organizations.sources.webhook-events.index')]
#[ApiAuthenticated]
#[ApiScope('webhooks:read')]
#[ApiOrganizationContext]
#[Can('sources.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class SourceWebhookEventsIndexHandler implements RequestHandlerInterface
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

        $events = [];
        foreach ($this->webhookApiFacade->recentEvents($source->id, $this->limit($request)) as $webhookEventDto) {
            $events[] = WebhookEventResource::fromDto($webhookEventDto);
        }

        return $this->apiResponseFactory->json([
            'data' => $events,
        ]);
    }

    private function limit(ServerRequestInterface $serverRequest): int
    {
        $limit = $serverRequest->getQueryParams()['limit'] ?? 20;

        return is_numeric($limit) ? max(1, min(100, (int) $limit)) : 20;
    }
}
