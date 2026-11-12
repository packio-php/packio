<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter;

use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Source\Application\Port\OrganizationQueryPort;

final readonly class OrganizationQueryAdapter implements OrganizationQueryPort
{
    public function __construct(private OrganizationApiFacadeInterface $organizationApiFacade) {}

    public function resolveOrganizationId(string $slug): ?string
    {
        if ('' === $slug) {
            return '';
        }

        $organization = $this->organizationApiFacade->findOrganizationBySlug($slug);

        return $organization?->id;
    }
}
