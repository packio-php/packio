<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Package\Infrastructure\Page\PackageIndexFilterCriteria;
use Packio\Package\Infrastructure\Page\PackagesPageDataBuilder;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
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
#[Can('packages.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get('/organization/:organization/packages', name: 'packages.index', middleware: [RequireExistingOrganizationMiddleware::class])]
final readonly class PackagesHandler implements RequestHandlerInterface
{
    public function __construct(private PackagesPageDataBuilder $packagesPageDataBuilder) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var InertiaInterface $inertia */
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $queryParams     = $request->getQueryParams();
        $page            = $this->queryString($queryParams['page'] ?? null);
        $perPage         = $this->queryString($queryParams['per_page'] ?? null, '18');
        $search          = $this->queryString($queryParams['search'] ?? null);

        $pageData = $this->packagesPageDataBuilder->build(
            $organizationDto,
            PaginationCriteria::fromArray([
                'page'     => $page,
                'per_page' => $perPage,
                'search'   => $search,
            ]),
            PackageIndexFilterCriteria::fromArray($queryParams),
        );

        return $inertia->render('Packages/Index', $pageData);
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
