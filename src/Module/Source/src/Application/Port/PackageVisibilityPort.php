<?php

declare(strict_types=1);

namespace Packio\Source\Application\Port;

use Packio\Shared\Domain\Organization\ActorContext;

interface PackageVisibilityPort
{
    public function updateVisibilityBySourceIdInContext(ActorContext $actorContext, string $sourceId, string $visibility): void;
}
