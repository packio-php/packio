<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

final readonly class SourceOAuthRefreshProviderOptions
{
    public function __construct(
        public ?string $refreshBefore = null,
        public ?string $idleBefore = null,
        public ?string $minimumRefreshInterval = null,
    ) {}
}
