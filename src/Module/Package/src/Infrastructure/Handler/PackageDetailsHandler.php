<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Package\Infrastructure\Page\PackageDetailsPageDataBuilder;
use Packio\Package\Infrastructure\Request\PackageDetailsRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Authenticated]
#[Can('packages.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get('/organization/:organization/packages/:vendor/:package', name: 'packages.show', middleware: [RequireExistingOrganizationMiddleware::class])]
#[MapRequest(route: PackageDetailsRequest::class)]
#[ValidateMappedRequest(PackageDetailsRequest::class)]
final readonly class PackageDetailsHandler implements RequestHandlerInterface
{
    public function __construct(
        private PackageApiFacadeInterface $packageApiFacade,
        private PackageDetailsPageDataBuilder $packageDetailsPageDataBuilder,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var InertiaInterface $inertia */
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        /** @var PackageDetailsRequest $route */
        $route       = $request->getAttribute(PackageDetailsRequest::class);
        $packageName = $route->vendor . '/' . $route->package;

        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $membership      = RequestContextAttributes::currentMembership($request);

        $package = $this->packageApiFacade->findPackageInOrganization(
            $organizationDto->id,
            $packageName,
        );

        if (! $package instanceof PackageDto) {
            $this->inertiaNotificationMessenger->flash($request, 'error', 'Package not found.');

            return new RedirectResponse('/organization/' . $organizationDto->slug . '/packages');
        }

        $pageData = $this->packageDetailsPageDataBuilder->build($organizationDto, $package, $membership);

        return $inertia->render('Packages/Show', $pageData);
    }
}
