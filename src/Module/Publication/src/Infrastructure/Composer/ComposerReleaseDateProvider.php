<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Composer;

use Throwable;

use function in_array;

final readonly class ComposerReleaseDateProvider implements ComposerReleaseDateProviderInterface
{
    public function __construct(private ComposerReleaseDateResolverInterface $composerReleaseDateResolver) {}

    /**
     * @param array<string, mixed> $source
     */
    public function resolve(array $source, ?string $accessToken, string $fallbackReleasedAt): ComposerReleaseDateMap
    {
        if ($this->shouldUseFallback($source)) {
            return ComposerReleaseDateMap::fallback($fallbackReleasedAt);
        }

        try {
            return $this->composerReleaseDateResolver->resolve($source, $accessToken);
        } catch (Throwable) {
            return ComposerReleaseDateMap::fallback($fallbackReleasedAt);
        }
    }

    /**
     * @param array<string, mixed> $source
     */
    private function shouldUseFallback(array $source): bool
    {
        // Composer bypasses OutboundUrlPolicy, so only fixed public hosts are allowed.
        return ! in_array($source['provider'] ?? '', ['github', 'gitlab', 'bitbucket', 'codeberg'], true);
    }
}
