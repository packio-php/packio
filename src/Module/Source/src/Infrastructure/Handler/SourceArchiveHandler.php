<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Policy\SourcePolicy;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Infrastructure\Request\SourceArchiveRequest;
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
    '/organization/:organization/sources/:sourceId/archive',
    name: 'sources.archive',
)]
#[MapRequest(body: SourceArchiveRequest::class)]
final readonly class SourceArchiveHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private PackageApiFacadeInterface $packageApiFacade,
        private SourcePolicy $sourcePolicy,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto      = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext         = RequestContextAttributes::requireCurrentOrganizationActorContext($request);
        $sourceId             = RouteAttributes::requireString($request, 'sourceId');

        /** @var SourceArchiveRequest $form */
        $form     = $request->getAttribute(SourceArchiveRequest::class);
        $archived = '1' === $form->archived;

        $existing = $this->sourceApiFacade->findSource($sourceId);
        $source   = $this->sourcePolicy->belongsToOrganization($existing, $organizationDto)
            ? ($archived
                ? $this->sourceApiFacade->archiveInContext($actorContext, $sourceId)
                : $this->sourceApiFacade->restoreInContext($actorContext, $sourceId))
            : null;

        if ($source instanceof SourceDto) {
            $this->packageApiFacade->updateActivityBySourceIdInContext($actorContext, $source->id, ! $archived);
        }

        $this->inertiaNotificationMessenger->flash(
            $request,
            $source instanceof SourceDto ? 'success' : 'error',
            $source instanceof SourceDto
                ? ($archived ? 'Source archived.' : 'Source restored to active integrations.')
                : 'Source not found.',
        );

        return new RedirectResponse(
            $source instanceof SourceDto
                ? '/organization/' . $organizationDto->slug . '/sources/' . $sourceId
                : '/organization/' . $organizationDto->slug . '/sources',
        );
    }
}
