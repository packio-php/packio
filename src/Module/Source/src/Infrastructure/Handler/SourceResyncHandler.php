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
use Throwable;

#[Post('/organization/:organization/sources/:sourceId/resync', name: 'sources.resync')]
#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
final readonly class SourceResyncHandler implements RequestHandlerInterface
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

        $source = '' === $sourceId ? null : $this->sourceApiFacade->findSource($sourceId);
        if (
            ! $source instanceof SourceDto
            || ! $this->sourcePolicy->belongsToOrganization($source, $organizationDto)
        ) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Source not found.');

            return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources');
        }

        if (! $source->active) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Source is inactive. Enable it before running a manual re-sync.');

            return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources/' . $sourceId);
        }

        try {
            $this->sourceApiFacade->scheduleSourceSync($source->id, $organizationDto->id, 5);
            $this->inertiaNotificationMessenger->flash($request, 'success', 'Re-sync scheduled. Refresh this page to see the latest sync status.');
        } catch (Throwable) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Unable to schedule re-sync.');
        }

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources/' . $sourceId);
    }
}
