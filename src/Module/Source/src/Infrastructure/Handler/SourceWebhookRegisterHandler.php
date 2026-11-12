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
#[Post('/organization/:organization/sources/:sourceId/webhook/register', name: 'sources.webhook.register')]
final readonly class SourceWebhookRegisterHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourcePolicy $sourcePolicy,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        RequestContextAttributes::requireCurrentOrganizationActorContext($request);
        $sourceId        = RouteAttributes::uuid($request, 'sourceId');

        $existing = '' === $sourceId ? null : $this->sourceApiFacade->findSource($sourceId);
        if (
            ! $existing instanceof SourceDto
            || ! $this->sourcePolicy->belongsToOrganization($existing, $organizationDto)
        ) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'Source not found.');

            return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources');
        }

        if (! $this->sourceApiFacade->supportsWebhookAutoRegistration($existing->provider)) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'Automatic webhook registration is not supported for this provider.');

            return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources/' . $sourceId);
        }

        $webhookRegistrationResultDto = $this->sourceApiFacade->tryRegisterWebhook($sourceId);

        if ($webhookRegistrationResultDto->success && $webhookRegistrationResultDto->alreadyExists) {
            $this->inertiaNotificationMessenger->toast($request, 'info', 'Webhook is already registered at the upstream provider.');
        } elseif ($webhookRegistrationResultDto->success) {
            $this->inertiaNotificationMessenger->toast($request, 'success', 'Webhook registered successfully at the upstream provider.');
        } elseif ('reauthorization_required' === $webhookRegistrationResultDto->authHealth) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'Provider authorization is invalid or expired. Please reconnect.');
        } else {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'Could not register webhook automatically.');
        }

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources/' . $sourceId);
    }
}
