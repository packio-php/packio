<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Policy\SourcePolicy;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post('/organization/:organization/sources/:sourceId/webhook/rotate', name: 'sources.webhook.rotate')]
final readonly class SourceWebhookRotateHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourcePolicy $sourcePolicy,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto      = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext         = RequestContextAttributes::requireCurrentOrganizationActorContext($request);
        $sourceId             = RouteAttributes::requireString($request, 'sourceId');

        // the redirect keeps the raw route value; only the facade needs a canonical UUID
        $sourceUuid = RouteAttributes::uuid($request, 'sourceId');
        $existing   = '' === $sourceUuid ? null : $this->sourceApiFacade->findSource($sourceUuid);
        $source     = $this->sourcePolicy->belongsToOrganization($existing, $organizationDto)
            ? $this->sourceApiFacade->rotateWebhookSecretInContext($actorContext, $sourceUuid)
            : null;

        $this->inertiaNotificationMessenger->flash(
            $request,
            $source instanceof SourceDto ? 'success' : 'error',
            $source instanceof SourceDto ? 'Webhook secret rotated.' : 'Source not found.',
        );

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources/' . $sourceId);
    }
}
