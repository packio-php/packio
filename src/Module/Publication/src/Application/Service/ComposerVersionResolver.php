<?php

declare(strict_types=1);

namespace Packio\Publication\Application\Service;

use function array_key_exists;
use function is_string;
use function ltrim;
use function preg_match;

final readonly class ComposerVersionResolver
{
    /**
     * @param array<string, mixed> $composer
     */
    public function resolve(array $composer, string $reference, string $defaultBranch): string
    {
        $composerVersion = $composer['version'] ?? null;
        if (is_string($composerVersion) && '' !== $composerVersion) {
            return $composerVersion;
        }

        if (preg_match('/^v?(\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?)$/', $reference, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^v?(\d+(?:\.\d+)?(?:[-+][A-Za-z0-9.-]+)?)$/', $reference, $matches)) {
            return $matches[1];
        }

        if (
            array_key_exists('extra', $composer)
            && isset($composer['extra']['branch-alias'])
            && is_string($composer['extra']['branch-alias'][$reference] ?? null)
        ) {
            return $composer['extra']['branch-alias'][$reference];
        }

        if (
            array_key_exists('extra', $composer)
            && isset($composer['extra']['branch-alias'])
            && is_string($composer['extra']['branch-alias']['dev-' . $reference] ?? null)
        ) {
            return $composer['extra']['branch-alias']['dev-' . $reference];
        }

        if (preg_match('/^v?(\d+(?:\.\d+)*)\.x$/', $reference, $matches)) {
            return $matches[1] . '.x-dev';
        }

        if ($reference === $defaultBranch) {
            return 'dev-' . $defaultBranch;
        }

        return 'dev-' . ltrim($reference, '/');
    }
}
