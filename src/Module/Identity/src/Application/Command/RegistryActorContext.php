<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command;

use function trim;

final readonly class RegistryActorContext
{
    public function __construct(public string $organizationId, public string $actorId, public string $actorRole) {}

    public function isOwner(): bool
    {
        return 'owner' === $this->actorRole;
    }

    public function isValid(): bool
    {
        return '' !== trim($this->organizationId)
            && '' !== trim($this->actorId)
            && $this->isOwner();
    }
}
