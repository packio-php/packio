<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure;

use InvalidArgumentException;

use function preg_match;
use function preg_replace;
use function sprintf;
use function trim;

final class CodebergRepositoryUrlParser
{
    /**
     * @return array{owner: string, repository: string, normalized_url: string}
     */
    public function parse(string $url): array
    {
        $normalized = (string) preg_replace('#\.git$#', '', trim($url));

        if (1 === preg_match('#^https://codeberg\.org/(?P<owner>[^/]+)/(?P<repository>[^/]+)$#', $normalized, $matches)) {
            return $this->result($matches['owner'], $matches['repository']);
        }

        if (1 === preg_match('#^git@codeberg\.org:(?P<owner>[^/]+)/(?P<repository>[^/]+)$#', $normalized, $matches)) {
            return $this->result($matches['owner'], $matches['repository']);
        }

        throw new InvalidArgumentException('Only codeberg.org repository URLs are supported for this flow.');
    }

    /**
     * @return array{owner: string, repository: string, normalized_url: string}
     */
    private function result(string $owner, string $repository): array
    {
        $owner      = trim($owner);
        $repository = trim($repository);

        if ('' === $owner || '' === $repository) {
            throw new InvalidArgumentException('A Codeberg repository URL must include an owner and repository name.');
        }

        return [
            'owner'          => $owner,
            'repository'     => $repository,
            'normalized_url' => sprintf('https://codeberg.org/%s/%s', $owner, $repository),
        ];
    }
}
