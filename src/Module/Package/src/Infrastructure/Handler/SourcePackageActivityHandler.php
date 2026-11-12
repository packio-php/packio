<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Package\Infrastructure\Request\SourcePackageActivityRequest;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Policy\SourcePolicy;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

use function count;
use function sprintf;

#[Post('/organization/:organization/sources/:sourceId/packages/activity', name: 'sources.packages.activity')]
#[Authenticated]
#[Can('packages.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[MapRequest(body: SourcePackageActivityRequest::class)]
final readonly class SourcePackageActivityHandler implements RequestHandlerInterface
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
        $sourceId             = RouteAttributes::uuid($request, 'sourceId');

        $source = '' === $sourceId ? null : $this->sourceApiFacade->findSource($sourceId);
        if (
            ! $source instanceof SourceDto
            || ! $this->sourcePolicy->belongsToOrganization($source, $organizationDto)
        ) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Source not found.');

            return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources');
        }

        /** @var SourcePackageActivityRequest $form */
        $form   = $request->getAttribute(SourcePackageActivityRequest::class);
        $active = '1' === $form->active;

        $updated = $this->packageApiFacade->updateActivityBySourceIdInContext($actorContext, $source->id, $active);

        $this->inertiaNotificationMessenger->flash(
            $request,
            'success',
            $active
                ? sprintf('Returned %d package(s) to registry listing.', count($updated))
                : sprintf('Hidden %d package(s) from registry listing.', count($updated)),
        );

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources/' . $sourceId);
    }
}
