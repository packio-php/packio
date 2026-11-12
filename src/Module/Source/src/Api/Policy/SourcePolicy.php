<?php

declare(strict_types=1);

namespace Packio\Source\Api\Policy;

use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Source\Api\Dto\SourceDto;

final class SourcePolicy
{
    public function belongsToOrganization(?SourceDto $sourceDto, OrganizationDto $organizationDto): bool
    {
        return $sourceDto instanceof SourceDto
            && $sourceDto->organizationId === $organizationDto->id;
    }
}
