<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Source\Infrastructure\Page\SourceIndexFilterCriteria;
use Packio\Source\Infrastructure\Page\SourcesPageDataBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function is_scalar;

#[Authenticated]
#[Can('sources.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get(
    '/organization/:organization/sources',
    name: 'sources.index',
    middleware: [RequireExistingOrganizationMiddleware::class]
)]
final readonly class SourcesHandler implements RequestHandlerInterface
{
    public function __construct(private SourcesPageDataBuilder $sourcesPageDataBuilder) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var InertiaInterface $inertia */
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $membership      = RequestContextAttributes::currentMembership($request);
        $queryParams     = $request->getQueryParams();
        $page            = $this->queryString($queryParams['page'] ?? null);
        $perPage         = $this->queryString($queryParams['per_page'] ?? null, '18');
        $search          = $this->queryString($queryParams['search'] ?? null);

        $membership = $membership instanceof OrganizationMemberDto
            ? $membership
            : null;

        $pageData = $this->sourcesPageDataBuilder->build(
            $organizationDto,
            $membership,
            PaginationCriteria::fromArray([
                'page'     => $page,
                'per_page' => $perPage,
                'search'   => $search,
            ]),
            SourceIndexFilterCriteria::fromArray($queryParams),
        );

        return $inertia->render('Sources/Index', $pageData);
    }

    private function queryString(mixed $value, string $default = ''): string
    {
        if (! is_scalar($value)) {
            return $default;
        }

        $value = (string) $value;

        return '' === $value ? $default : $value;
    }
}
