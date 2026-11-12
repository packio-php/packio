<?php

declare(strict_types=1);

namespace Packio\Shared\Api\Dto;

final readonly class OrganizationMemberDto
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $userId,
        public string $role,
        public string $createdAt = '',
        public string $updatedAt = '',
    ) {}
}
