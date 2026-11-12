<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Git;

use function array_keys;
use function explode;
use function preg_match;
use function trim;

final readonly class GitReferenceParser
{
    /**
     * @return array{default_branch: string, tags: list<GitReference>, branches: list<GitReference>}
     */
    public static function parseLsRemote(string $output): array
    {
        $defaultBranch = 'main';
        $tags          = [];
        $peeledTags    = [];
        $branches      = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }

            if (1 === preg_match('/^ref:\s+refs\/heads\/([^\s]+)\s+HEAD$/', $line, $matches)) {
                $defaultBranch = $matches[1];

                continue;
            }

            if (1 === preg_match('/^([a-f0-9]+)\s+refs\/tags\/(.+?)(\^\{\})?$/', $line, $matches)) {
                $name = $matches[2];
                if (isset($matches[3])) {
                    $peeledTags[$name] = $matches[1];
                } else {
                    $tags[$name] = $matches[1];
                }

                continue;
            }

            if (1 === preg_match('/^([a-f0-9]+)\s+refs\/heads\/(.+)$/', $line, $matches)) {
                $branches[] = new GitReference($matches[2], $matches[1], GitReference::TYPE_BRANCH);
            }
        }

        $resolvedTags = [];
        foreach (array_keys($tags + $peeledTags) as $name) {
            $resolvedTags[] = new GitReference(
                $name,
                $peeledTags[$name] ?? $tags[$name],
                GitReference::TYPE_TAG,
            );
        }

        return [
            'default_branch' => $defaultBranch,
            'tags'           => $resolvedTags,
            'branches'       => $branches,
        ];
    }
}
