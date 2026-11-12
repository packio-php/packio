<?php

declare(strict_types=1);

$buildDir  = __DIR__ . '/../public/build';
$arguments = $_SERVER['argv'] ?? [];

foreach ($arguments as $argument) {
    if (\str_starts_with($argument, '--build-dir=')) {
        $buildDir = \substr($argument, 12);
    }
}

if (\in_array('--help', $arguments, true)) {
    echo "Usage: php bin/verify-vite-assets.php [--build-dir=/path/to/public/build]\n";

    exit(0);
}

$buildDir     = \rtrim($buildDir, '/');
$manifestPath = $buildDir . '/.vite/manifest.json';

if (! \is_file($manifestPath)) {
    \fwrite(STDERR, \sprintf("Vite manifest not found: %s\n", $manifestPath));

    exit(1);
}

$manifestJson = \file_get_contents($manifestPath);
if (false === $manifestJson) {
    \fwrite(STDERR, \sprintf("Unable to read Vite manifest: %s\n", $manifestPath));

    exit(1);
}

$manifest = \json_decode($manifestJson, true);
if (! \is_array($manifest)) {
    \fwrite(STDERR, \sprintf("Invalid Vite manifest JSON: %s\n", $manifestPath));

    exit(1);
}

$missing        = [];
$checked        = [];
$visitedEntries = [];

$checkAsset = static function(string $asset, string $entry, string $field) use ($buildDir, &$missing, &$checked): void {
    $asset           = \ltrim($asset, '/');
    $checked[$asset] = true;

    if (! \is_file($buildDir . '/' . $asset)) {
        $missing[] = \sprintf('%s references missing %s asset: %s', $entry, $field, $asset);
    }
};

$checkEntry = static function(string $entryKey) use (
    &$checkEntry,
    $checkAsset,
    $manifest,
    &$missing,
    &$visitedEntries,
): void {
    if (isset($visitedEntries[$entryKey])) {
        return;
    }

    $visitedEntries[$entryKey] = true;

    if (! isset($manifest[$entryKey]) || ! \is_array($manifest[$entryKey])) {
        $missing[] = \sprintf('Manifest references missing entry: %s', $entryKey);

        return;
    }

    $entry = $manifest[$entryKey];

    if (isset($entry['file']) && \is_string($entry['file'])) {
        $checkAsset($entry['file'], $entryKey, 'file');
    }

    foreach (['css', 'assets'] as $field) {
        if (! isset($entry[$field]) || ! \is_array($entry[$field])) {
            continue;
        }

        foreach ($entry[$field] as $asset) {
            if (\is_string($asset)) {
                $checkAsset($asset, $entryKey, $field);
            }
        }
    }

    foreach (['imports', 'dynamicImports'] as $field) {
        if (! isset($entry[$field]) || ! \is_array($entry[$field])) {
            continue;
        }

        foreach ($entry[$field] as $importedEntry) {
            if (\is_string($importedEntry)) {
                $checkEntry($importedEntry);
            }
        }
    }
};

foreach (\array_keys($manifest) as $entryKey) {
    if (\is_string($entryKey)) {
        $checkEntry($entryKey);
    }
}

if ([] !== $missing) {
    \fwrite(STDERR, "Vite manifest references missing build assets:\n");

    foreach ($missing as $message) {
        \fwrite(STDERR, '- ' . $message . "\n");
    }

    exit(1);
}

echo \sprintf(
    "Verified %d Vite build asset(s) from %s\n",
    \count($checked),
    $manifestPath,
);
