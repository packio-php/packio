<?php

declare(strict_types=1);

namespace Packio\Shared\Domain\Organization;

/**
 * Domain value object representing the context of an acting user within an organization.
 *
 * This is the pure domain version, free from Api-layer dependencies.
 * For creating from Api DTOs, use the factory in Infrastructure.
 */
readonly class ActorContext
{
    public function __construct(public string $organizationId, public string $actorId, public string $actorRole) {}

    public function isOwner(): bool
    {
        return 'owner' === $this->actorRole;
    }
}
