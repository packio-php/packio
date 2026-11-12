<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

final readonly class MembershipData
{
    public function __construct(public string $organizationId, public string $userId, public string $role) {}
}
