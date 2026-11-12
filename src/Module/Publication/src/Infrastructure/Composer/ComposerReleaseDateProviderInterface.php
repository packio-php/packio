<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Composer;

interface ComposerReleaseDateProviderInterface
{
    /**
     * @param array<string, mixed> $source
     */
    public function resolve(array $source, ?string $accessToken, string $fallbackReleasedAt): ComposerReleaseDateMap;
}
