<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceSettingsPatch;
use Packio\Source\Api\Dto\SourceSettingsResultDto;

interface SourceSettingsUpdaterInterface
{
    /** Atomically update the source and the visibility of its packages. */
    public function update(ActorContext $actorContext, string $sourceId, SourceSettingsPatch $sourceSettingsPatch): SourceSettingsResultDto;
}
