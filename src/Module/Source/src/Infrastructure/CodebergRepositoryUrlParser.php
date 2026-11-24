<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure;

use InvalidArgumentException;

use function parse_url;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function rtrim;
use function sprintf;
use function trim;

final class CodebergRepositoryUrlParser
{
    /**
     * @return array{owner: string, repository: string, normalized_url: string, base_url: string}
     */
    public function parse(string $url, string $baseUrl = 'https://codeberg.org'): array
    {
        $normalized = (string) preg_replace('#\.git$#', '', trim($url));
        $baseUrl    = rtrim($baseUrl, '/');

        if (1 === preg_match('#^' . preg_quote($baseUrl, '#') . '/(?P<owner>[^/]+)/(?P<repository>[^/]+)$#', $normalized, $matches)) {
            return $this->result($matches['owner'], $matches['repository'], $baseUrl);
        }

        // SSH form carries no path, so it only maps to an instance served from the host root.
        $parts = parse_url($baseUrl);
        if (! isset($parts['path']) && isset($parts['host'])
            && 1 === preg_match('#^git@' . preg_quote($parts['host'], '#') . ':(?P<owner>[^/]+)/(?P<repository>[^/]+)$#', $normalized, $matches)) {
            return $this->result($matches['owner'], $matches['repository'], $baseUrl);
        }

        throw new InvalidArgumentException(sprintf('Only %s repository URLs are supported for this flow.', preg_replace('#^https?://#', '', $baseUrl)));
    }

    /**
     * @return array{owner: string, repository: string, normalized_url: string, base_url: string}
     */
    private function result(string $owner, string $repository, string $baseUrl): array
    {
        $owner      = trim($owner);
        $repository = trim($repository);

        if ('' === $owner || '' === $repository) {
            throw new InvalidArgumentException('A Codeberg repository URL must include an owner and repository name.');
        }

        return [
            'owner'          => $owner,
            'repository'     => $repository,
            'normalized_url' => sprintf('%s/%s/%s', $baseUrl, $owner, $repository),
            'base_url'       => $baseUrl,
        ];
    }
}
