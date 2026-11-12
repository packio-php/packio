<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

final readonly class SourceSettingsResultDto
{
    public function __construct(public SourceSettingsStatus $status, public ?SourceDto $source = null) {}

    public function isUpdated(): bool
    {
        return SourceSettingsStatus::Updated === $this->status;
    }

    public function isForbidden(): bool
    {
        return SourceSettingsStatus::Forbidden === $this->status;
    }
}
