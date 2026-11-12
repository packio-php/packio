<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

use Packio\Source\Api\Exception\InvalidSourceSettings;

/** Null means omitted; explicit null in request data is invalid. */
final readonly class SourceSettingsPatch
{
    public function __construct(
        public ?string $visibility = null,
        public ?bool $active = null,
        public ?int $keepLastReleases = null,
    ) {
        if (null !== $visibility && 'public' !== $visibility && 'private' !== $visibility) {
            throw new InvalidSourceSettings([
                'visibility' => ['Visibility must be public or private.'],
            ]);
        }

        if (null !== $keepLastReleases && $keepLastReleases < 0) {
            throw new InvalidSourceSettings([
                'keep_last_releases' => ['Keep last releases must be a non-negative integer.'],
            ]);
        }
    }
}
