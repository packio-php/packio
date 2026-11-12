<?php

declare(strict_types=1);

namespace Packio\Organization\Api\Dto;

final readonly class OrganizationDeletionContext
{
    public function __construct(
        public string $organizationId,
        public string $actorId = '',
        public string $origin = 'organization',
        public string $auditEvent = 'organization.delete.initiated',
    ) {}
}
