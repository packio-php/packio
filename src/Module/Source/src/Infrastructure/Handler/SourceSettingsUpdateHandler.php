<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Infrastructure\Request\SourceSettingsRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post('/organization/:organization/sources/:sourceId/settings', name: 'sources.settings.update')]
#[MapRequest(source: SourceSettingsRequest::class)]
#[ValidateMappedRequest(SourceSettingsRequest::class)]
#[ValidationFailed(
    redirectTo: '/organization/{organization}/sources/{sourceId}',
    message: 'Invalid request data.',
    notify: ValidationNotify::Flash,
)]
final readonly class SourceSettingsUpdateHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto      = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext         = RequestContextAttributes::requireCurrentOrganizationActorContext($request);
        $sourceId             = RouteAttributes::requireString($request, 'sourceId');

        /** @var SourceSettingsRequest $form */
        $form = $request->getAttribute(SourceSettingsRequest::class);

        // the redirect keeps the raw route value; only the facade needs a canonical UUID
        $sourceUuid = RouteAttributes::uuid($request, 'sourceId');
        $updated    = '' !== $sourceUuid
            && $this->sourceApiFacade->updateSourceSettings($actorContext, $sourceUuid, $form->toPatch())->isUpdated();
        $this->inertiaNotificationMessenger->flash(
            $request,
            $updated ? 'success' : 'error',
            $updated ? 'Source settings updated.' : 'Source not found.',
        );

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources/' . $sourceId);
    }
}
