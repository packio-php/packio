<?php

declare(strict_types=1);

namespace Packio\Application\Release;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

use function array_filter;
use function array_map;
use function array_values;
use function dirname;
use function escapeshellarg;
use function explode;
use function is_callable;
use function is_string;
use function ltrim;
use function max;
use function preg_match;
use function shell_exec;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function trim;
use function usort;
use function version_compare;

final class AppReleaseMetadataGenerator
{
    private const string SEMVER_TAG_PATTERN = '/^v(?<major>0|[1-9]\d*)\.(?<minor>0|[1-9]\d*)\.(?<patch>0|[1-9]\d*)$/';

    /** @var callable(string): string */
    private $run;

    /** @param null|callable(string): string $run */
    public function __construct(private string $basePath = '', ?callable $run = null, private readonly ?DateTimeImmutable $builtAt = null)
    {
        $this->basePath = '' !== $basePath ? $basePath : dirname(__DIR__, 4);
        $this->run      = is_callable($run) ? $run : $this->runGit(...);
    }

    /** @return array<string, bool|int|string> */
    public function generate(): array
    {
        $sha = $this->command('rev-parse --short HEAD');
        if ('' === $sha) {
            throw new RuntimeException('Unable to read Git HEAD for release metadata. Check Git availability, repository ownership and checkout history.');
        }

        $branch   = $this->command('rev-parse --abbrev-ref HEAD');
        $dirty    = '' !== $this->command('status --porcelain');
        $exactTag = $this->highestSemverTag($this->command("tag --points-at HEAD --list 'v[0-9]*.[0-9]*.[0-9]*'"));

        if (null !== $exactTag && ! $dirty) {
            $version = $this->stripTagPrefix($exactTag);

            return $this->payload($version, 'stable', $sha, $branch, 0, $dirty, $exactTag);
        }

        $latestTag   = $this->latestReachableTag();
        $distance    = $this->distanceSince($latestTag);
        $baseVersion = null !== $latestTag ? $this->stripTagPrefix($latestTag) : '0.0.0';
        $nextVersion = $this->incrementVersion($baseVersion, $this->resolveBump($latestTag));
        $build       = $sha . ($dirty ? '.dirty' : '');
        $version     = sprintf('%s-dev.%d+%s', $nextVersion, max(1, $distance), $build);

        return $this->payload($version, 'dev', $sha, $branch, $distance, $dirty, $latestTag ?? '');
    }

    private function latestReachableTag(): ?string
    {
        $tag = $this->command("describe --tags --abbrev=0 --match 'v[0-9]*.[0-9]*.[0-9]*' HEAD");

        return $this->isSemverTag($tag) ? $tag : null;
    }

    private function distanceSince(?string $tag): int
    {
        $range    = null !== $tag ? escapeshellarg($tag) . '..HEAD' : 'HEAD';
        $distance = $this->command('rev-list --count ' . $range);

        return (int) $distance;
    }

    private function resolveBump(?string $latestTag): string
    {
        $range    = null !== $latestTag ? escapeshellarg($latestTag) . '..HEAD' : 'HEAD';
        $subjects = $this->lines($this->command('log --format=%s ' . $range));
        $bodies   = $this->lines($this->command('log --format=%b ' . $range));
        $bump     = 'patch';

        foreach ([...$subjects, ...$bodies] as $line) {
            if (str_contains($line, 'BREAKING CHANGE') || 1 === preg_match('/^[a-z]+(?:\([^)]+\))?!:/', $line)) {
                return 'major';
            }

            if (str_starts_with($line, 'feat:') || 1 === preg_match('/^feat\([^)]+\):/', $line)) {
                $bump = 'minor';
            }
        }

        return $bump;
    }

    private function incrementVersion(string $version, string $bump): string
    {
        if (1 !== preg_match('/^(?<major>0|[1-9]\d*)\.(?<minor>0|[1-9]\d*)\.(?<patch>0|[1-9]\d*)$/', $version, $matches)) {
            throw new RuntimeException(sprintf('Invalid semantic version "%s".', $version));
        }

        $major = (int) $matches['major'];
        $minor = (int) $matches['minor'];
        $patch = (int) $matches['patch'];

        return match ($bump) {
            'major' => sprintf('%d.0.0', $major + 1),
            'minor' => sprintf('%d.%d.0', $major, $minor + 1),
            default => sprintf('%d.%d.%d', $major, $minor, $patch + 1),
        };
    }

    private function highestSemverTag(string $tags): ?string
    {
        $semverTags = array_values(array_filter($this->lines($tags), $this->isSemverTag(...)));
        if ([] === $semverTags) {
            return null;
        }

        usort($semverTags, static fn (string $a, string $b): int => version_compare(ltrim($b, 'v'), ltrim($a, 'v')));

        return $semverTags[0];
    }

    /** @return list<string> */
    private function lines(string $output): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode("\n", $output)),
            static fn (string $line): bool => '' !== $line,
        ));
    }

    private function isSemverTag(string $tag): bool
    {
        return 1 === preg_match(self::SEMVER_TAG_PATTERN, $tag);
    }

    private function stripTagPrefix(string $tag): string
    {
        $tag = trim($tag);

        return str_starts_with($tag, 'v') ? ltrim($tag, 'v') : $tag;
    }

    /** @return array<string, bool|int|string> */
    private function payload(
        string $version,
        string $channel,
        string $sha,
        string $branch,
        int $distance,
        bool $dirty,
        string $tag,
    ): array {
        $builtAt = $this->builtAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return [
            'version'  => $version,
            'number'   => $version,
            'channel'  => $channel,
            'sha'      => $sha,
            'branch'   => 'HEAD' !== $branch ? $branch : '',
            'built_at' => $builtAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'distance' => $distance,
            'dirty'    => $dirty,
            'tag'      => $tag,
            'display'  => $version . ('' !== $sha ? sprintf(' (%s)', $sha) : ''),
        ];
    }

    private function command(string $arguments): string
    {
        $run = $this->run;

        return trim($run($arguments));
    }

    private function runGit(string $arguments): string
    {
        $output = shell_exec(sprintf('git -C %s %s 2>/dev/null', escapeshellarg($this->basePath), $arguments));

        return is_string($output) ? trim($output) : '';
    }
}
