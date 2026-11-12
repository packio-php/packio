<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Page;

use Packio\Shared\Api\Dto\OrganizationDto;

final readonly class AdminOrganizationPresenter
{
    /**
     * @return array{id: string, slug: string, name: string, status: string}
     */
    public function present(OrganizationDto $organizationDto): array
    {
        return [
            'id'     => $organizationDto->id,
            'slug'   => $organizationDto->slug,
            'name'   => $organizationDto->name,
            'status' => $organizationDto->status,
        ];
    }
}
