<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter;

use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Application\Port\PackageVisibilityPort;

final readonly class PackageVisibilityAdapter implements PackageVisibilityPort
{
    public function __construct(private PackageApiFacadeInterface $packageApiFacade) {}

    public function updateVisibilityBySourceIdInContext(ActorContext $actorContext, string $sourceId, string $visibility): void
    {
        $this->packageApiFacade->updateVisibilityBySourceIdInContext($actorContext, $sourceId, $visibility);
    }
}
