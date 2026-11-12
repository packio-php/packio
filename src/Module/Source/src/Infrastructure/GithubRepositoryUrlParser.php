<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure;

use InvalidArgumentException;

use function preg_match;
use function preg_replace;
use function sprintf;
use function trim;

final class GithubRepositoryUrlParser
{
    /**
     * @return array{owner: string, repository: string, normalized_url: string}
     */
    public function parse(string $url): array
    {
        $normalized = (string) preg_replace('#\.git$#', '', trim($url));

        if (1 === preg_match('#^https://github\.com/(?P<owner>[^/]+)/(?P<repo>[^/]+)$#', $normalized, $matches)) {
            return [
                'owner'          => $matches['owner'],
                'repository'     => $matches['repo'],
                'normalized_url' => sprintf('https://github.com/%s/%s', $matches['owner'], $matches['repo']),
            ];
        }

        if (1 === preg_match('#^git@github\.com:(?P<owner>[^/]+)/(?P<repo>[^/]+)$#', $normalized, $matches)) {
            return [
                'owner'          => $matches['owner'],
                'repository'     => $matches['repo'],
                'normalized_url' => sprintf('https://github.com/%s/%s', $matches['owner'], $matches['repo']),
            ];
        }

        throw new InvalidArgumentException('Only GitHub repository URLs are supported for this flow.');
    }
}
