<?php

declare(strict_types=1);

use Packio\Application\Release\AppReleaseMetadataGenerator;

require \dirname(__DIR__) . '/vendor/autoload.php';

$arguments  = $_SERVER['argv'] ?? [];
$remote     = 'origin';
$dryRun     = false;
$push       = true;
$fetch      = true;
$allowDirty = false;
$message    = '';

foreach ($arguments as $argument) {
    if ('--help' === $argument) {
        echo <<<'HELP'
            Usage: php bin/create-release-tag.php [options]

            Creates and pushes an annotated release tag using the next semantic version
            computed from conventional commits.

            Options:
              --dry-run              Print the tag that would be created without changing git.
              --no-push              Create the tag locally without pushing it.
              --no-fetch             Do not fetch remote tags before computing the next version.
              --allow-dirty          Allow tagging when the working tree has uncommitted changes.
              --remote=<name>        Remote to push to. Defaults to "origin".
              --message=<message>    Annotated tag message. Defaults to "Release vX.Y.Z".
              --help                 Show this help.

            HELP;

        exit(0);
    }

    if ('--dry-run' === $argument) {
        $dryRun = true;

        continue;
    }

    if ('--no-push' === $argument) {
        $push = false;

        continue;
    }

    if ('--no-fetch' === $argument) {
        $fetch = false;

        continue;
    }

    if ('--allow-dirty' === $argument) {
        $allowDirty = true;

        continue;
    }

    if (\str_starts_with($argument, '--remote=')) {
        $remote = \trim(\substr($argument, 9));

        continue;
    }

    if (\str_starts_with($argument, '--message=')) {
        $message = \substr($argument, 10);
    }
}

if ('' === $remote) {
    \fail('Remote name cannot be empty.');
}

$basePath = \dirname(__DIR__);
if ($fetch) {
    \git($basePath, 'fetch --tags ' . \escapeshellarg($remote));
}

$metadata       = new AppReleaseMetadataGenerator($basePath)->generate();
$releaseVersion = \releaseVersion((string) ($metadata['version'] ?? ''));
$tag            = 'v' . $releaseVersion;
$message        = '' !== $message ? $message : 'Release ' . $tag;

if (! $allowDirty && (bool) ($metadata['dirty'] ?? false)) {
    \fail('Working tree has uncommitted changes. Commit or stash them before creating a release tag.');
}

if ('stable' === ($metadata['channel'] ?? '')) {
    \fail(\sprintf('HEAD is already tagged as %s.', (string) ($metadata['tag'] ?? $tag)));
}

if (\gitSucceeds($basePath, 'rev-parse -q --verify ' . \escapeshellarg('refs/tags/' . $tag))) {
    \fail(\sprintf('Tag %s already exists locally.', $tag));
}

if ($push && ! $dryRun) {
    $remoteTagCheck = \gitStatus($basePath, 'ls-remote --exit-code --tags ' . \escapeshellarg($remote) . ' ' . \escapeshellarg('refs/tags/' . $tag));
    if (0 === $remoteTagCheck['exitCode']) {
        \fail(\sprintf('Tag %s already exists on remote %s.', $tag, $remote));
    }

    if (2 !== $remoteTagCheck['exitCode']) {
        \fail(\sprintf("Unable to check remote tag %s on %s.\n%s", $tag, $remote, $remoteTagCheck['output']));
    }
}

echo \sprintf("Release version: %s\n", $releaseVersion);
echo \sprintf("Release tag: %s\n", $tag);
echo \sprintf("Source version: %s\n", (string) ($metadata['version'] ?? ''));
echo \sprintf("Source SHA: %s\n", (string) ($metadata['sha'] ?? ''));

if ($dryRun) {
    echo "Dry run: no tag was created.\n";

    exit(0);
}

\git($basePath, 'tag -a ' . \escapeshellarg($tag) . ' -m ' . \escapeshellarg($message));
echo \sprintf("Created local tag %s.\n", $tag);

if (! $push) {
    echo "Skipping push because --no-push was provided.\n";

    exit(0);
}

\git($basePath, 'push ' . \escapeshellarg($remote) . ' ' . \escapeshellarg($tag));
echo \sprintf("Pushed tag %s to %s.\n", $tag, $remote);

function releaseVersion(string $version): string
{
    if (1 !== \preg_match('/^(?<version>(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*))(?:[-+].*)?$/', $version, $matches)) {
        \fail(\sprintf('Cannot derive release version from "%s".', $version));
    }

    return $matches['version'];
}

function git(string $basePath, string $arguments, bool $failOnError = true): string
{
    $command  = \sprintf('git -C %s %s 2>&1', \escapeshellarg($basePath), $arguments);
    $output   = [];
    $exitCode = 0;

    \exec($command, $output, $exitCode);

    $result = \trim(\implode("\n", $output));
    if ($failOnError && 0 !== $exitCode) {
        \fail(\sprintf("Command failed: git %s\n%s", $arguments, $result));
    }

    return $result;
}

function gitSucceeds(string $basePath, string $arguments): bool
{
    return 0 === \gitStatus($basePath, $arguments)['exitCode'];
}

/** @return array{output: string, exitCode: int} */
function gitStatus(string $basePath, string $arguments): array
{
    $command  = \sprintf('git -C %s %s 2>&1', \escapeshellarg($basePath), $arguments);
    $output   = [];
    $exitCode = 0;

    \exec($command, $output, $exitCode);

    return [
        'output'   => \trim(\implode("\n", $output)),
        'exitCode' => $exitCode,
    ];
}

function fail(string $message): never
{
    \fwrite(STDERR, $message . "\n");

    exit(1);
}
