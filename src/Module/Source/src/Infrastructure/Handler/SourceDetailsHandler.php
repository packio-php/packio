<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Policy\SourcePolicy;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Infrastructure\Page\SourceDetailsPageDataBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Authenticated]
#[Can('sources.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get(
    '/organization/:organization/sources/:sourceId',
    name: 'sources.show',
    middleware: [RequireExistingOrganizationMiddleware::class]
)]
final readonly class SourceDetailsHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourcePolicy $sourcePolicy,
        private SourceDetailsPageDataBuilder $sourceDetailsPageDataBuilder,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var InertiaInterface $inertia */
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $membership      = RequestContextAttributes::currentMembership($request);

        $sourceId = RouteAttributes::uuid($request, 'sourceId');
        if ('' === $sourceId) {
            return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources');
        }

        $source = $this->sourceApiFacade->findSource($sourceId);
        if (
            ! $source instanceof SourceDto
            || 'deleting' === $source->status
            || ! $this->sourcePolicy->belongsToOrganization($source, $organizationDto)
        ) {
            return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources');
        }

        $membership = $membership instanceof OrganizationMemberDto
            ? $membership
            : null;

        $pageData = $this->sourceDetailsPageDataBuilder->build(
            $organizationDto,
            $membership,
            $source,
        );

        return $inertia->render('Sources/Show', $pageData);
    }
}
