<?php

declare(strict_types=1);

use Packio\Application\Release\AppReleaseMetadataGenerator;

require \dirname(__DIR__) . '/vendor/autoload.php';

$output    = \dirname(__DIR__) . '/data/build/release.json';
$arguments = $_SERVER['argv'] ?? [];

foreach ($arguments as $argument) {
    if (\str_starts_with($argument, '--output=')) {
        $output = \substr($argument, 9);
    }
}

if (\in_array('--help', $arguments, true)) {
    echo "Usage: php bin/generate-release-metadata.php [--output=/path/to/release.json]\n";

    exit(0);
}

// Production images carry metadata generated from Git during the build.
// Do not replace it with a synthetic version when the runtime has no checkout.
if (! \file_exists(\dirname(__DIR__) . '/.git')) {
    $existing = \is_file($output) ? \json_decode((string) \file_get_contents($output), true) : null;
    if (! \is_array($existing)
        || ! \is_string($existing['version'] ?? null)
        || 1 !== \preg_match('/^\d+\.\d+\.\d+(?:-dev\.\d+\+[a-f0-9]+(?:\.dirty)?)?$/D', $existing['version'])
        || ! \is_string($existing['sha'] ?? null)
        || 1 !== \preg_match('/^[a-f0-9]{4,64}$/D', $existing['sha'])
    ) {
        \fwrite(STDERR, "Git checkout and valid release metadata are both missing. Generate metadata before packaging.\n");

        exit(1);
    }

    echo \sprintf("Using packaged release metadata: %s\n", $existing['version']);

    exit(0);
}

try {
    $metadata = new AppReleaseMetadataGenerator(\dirname(__DIR__))->generate();
} catch (RuntimeException $error) {
    \fwrite(STDERR, $error->getMessage() . "\n");

    exit(1);
}
$directory = \dirname($output);
if (! \is_dir($directory) && ! \mkdir($directory, 0o755, true) && ! \is_dir($directory)) {
    \fwrite(STDERR, \sprintf("Unable to create release metadata directory: %s\n", $directory));

    exit(1);
}

$json = \json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (false === $json || false === \file_put_contents($output, $json . "\n")) {
    \fwrite(STDERR, \sprintf("Unable to write release metadata: %s\n", $output));

    exit(1);
}

echo \sprintf("Generated release metadata: %s\n", $metadata['version']);
