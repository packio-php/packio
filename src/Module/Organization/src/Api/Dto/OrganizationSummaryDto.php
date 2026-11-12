<?php

declare(strict_types=1);

namespace Packio\Organization\Api\Dto;

final readonly class OrganizationSummaryDto
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public bool $anonymousAccess,
        public bool $active,
        public string $status,
        public string $createdAt,
        public string $updatedAt,
        public int $memberCount,
        public int $invitationCount,
    ) {}
}
