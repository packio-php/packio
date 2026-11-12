<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Composer;

interface ComposerReleaseDateResolverInterface
{
    /**
     * @param array<string, mixed> $source
     */
    public function resolve(array $source, ?string $accessToken = null): ComposerReleaseDateMap;
}
