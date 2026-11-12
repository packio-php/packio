<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceSettingsPatch;
use Packio\Source\Api\Dto\SourceSettingsResultDto;
use Packio\Source\Api\Dto\SourceSettingsStatus;

use function trim;

final readonly class SourceSettingsService
{
    public function __construct(private SourceSettingsUpdaterInterface $sourceSettingsUpdater) {}

    public function update(ActorContext $actorContext, string $sourceId, SourceSettingsPatch $sourceSettingsPatch): SourceSettingsResultDto
    {
        if (! $actorContext->isOwner() || '' === trim($actorContext->organizationId) || '' === trim($actorContext->actorId)) {
            return new SourceSettingsResultDto(SourceSettingsStatus::Forbidden);
        }

        return $this->sourceSettingsUpdater->update($actorContext, $sourceId, $sourceSettingsPatch);
    }
}
