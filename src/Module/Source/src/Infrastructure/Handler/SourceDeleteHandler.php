<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Policy\SourcePolicy;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Infrastructure\Request\SourceDeleteRequest;
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
#[Post(
    '/organization/:organization/sources/:sourceId/delete',
    name: 'sources.delete',
)]
#[MapRequest(route: SourceDeleteRequest::class)]
#[ValidateMappedRequest(SourceDeleteRequest::class)]
#[ValidationFailed(
    redirectTo: '/organization/{organization}/sources',
    message: 'Invalid request data.',
    notify: ValidationNotify::Flash,
)]
final readonly class SourceDeleteHandler implements RequestHandlerInterface
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

        /** @var SourceDeleteRequest $route */
        $route = $request->getAttribute(SourceDeleteRequest::class);

        $source = $this->sourceApiFacade->findSource($route->sourceId);
        if (
            ! $source instanceof SourceDto
            || ! $this->sourcePolicy->belongsToOrganization($source, $organizationDto)
        ) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Source not found.');

            return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources');
        }

        $queued = $this->sourceApiFacade->scheduleSourceDeleteInContext($actorContext, $source->id);

        $this->inertiaNotificationMessenger->toast(
            $request,
            $queued ? 'success' : 'error',
            $queued ? 'Source will be permanently deleted.' : 'Source could not be queued for deletion.',
        );

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources');
    }
}
