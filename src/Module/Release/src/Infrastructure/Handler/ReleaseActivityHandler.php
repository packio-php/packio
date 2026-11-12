<?php

declare(strict_types=1);

namespace Packio\Release\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Release\Infrastructure\Request\ReleaseActivityRequest;
use Packio\Release\Infrastructure\Request\ReleaseActivityRouteRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/organization/:organization/packages/:vendor/:package/releases/activity', name: 'packages.releases.activity')]
#[Authenticated]
#[Can('packages.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[MapRequest(route: ReleaseActivityRouteRequest::class)]
#[MapRequest(body: ReleaseActivityRequest::class)]
#[ValidateMappedRequest(ReleaseActivityRouteRequest::class)]
#[ValidateMappedRequest(ReleaseActivityRequest::class)]
final readonly class ReleaseActivityHandler implements RequestHandlerInterface
{
    public function __construct(
        private ReleaseApiFacadeInterface $releaseApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto      = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext         = RequestContextAttributes::requireCurrentOrganizationActorContext($request);

        /** @var ReleaseActivityRouteRequest $route */
        $route       = $request->getAttribute(ReleaseActivityRouteRequest::class);
        $packageName = $route->vendor . '/' . $route->package;

        /** @var ReleaseActivityRequest $form */
        $form   = $request->getAttribute(ReleaseActivityRequest::class);
        $active = '1' === $form->active;

        $release = $this->releaseApiFacade->updateActivityInContext($actorContext, $packageName, $form->version, $active);

        $this->inertiaNotificationMessenger->flash(
            $request,
            $release instanceof ReleaseDto ? 'success' : 'error',
            $release instanceof ReleaseDto
                ? ($active ? 'Release returned to registry metadata.' : 'Release hidden from registry metadata.')
                : 'Release not found.',
        );

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/packages/' . $packageName);
    }
}
