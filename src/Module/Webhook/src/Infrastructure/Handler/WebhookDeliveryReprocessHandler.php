<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Policy\SourcePolicy;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Post('/organization/:organization/sources/webhooks/:sourceId/:deliveryId/reprocess', name: 'sources.webhooks.reprocess')]
#[Authenticated]
#[Can('webhooks.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class WebhookDeliveryReprocessHandler implements RequestHandlerInterface
{
    public function __construct(
        private WebhookApiFacadeInterface $webhookApiFacade,
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourcePolicy $sourcePolicy,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);

        $sourceId   = RouteAttributes::uuid($request, 'sourceId');
        $deliveryId = $request->getAttribute('deliveryId');

        if ('' === $sourceId) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'Source not found.');

            return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources');
        }

        $source = $this->sourceApiFacade->findSource($sourceId);
        if (
            ! $source instanceof SourceDto
            || ! $this->sourcePolicy->belongsToOrganization($source, $organizationDto)
        ) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'Source not found.');

            return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources');
        }

        $webhookResultDto = $this->webhookApiFacade->reprocessDelivery($sourceId, (string) $deliveryId);

        $this->inertiaNotificationMessenger->toast(
            $request,
            202 === $webhookResultDto->statusCode ? 'success' : 'error',
            202 === $webhookResultDto->statusCode
                ? 'Webhook delivery reprocessing queued.'
                : 'Unable to reprocess webhook delivery.',
        );

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources/' . $sourceId);
    }
}
