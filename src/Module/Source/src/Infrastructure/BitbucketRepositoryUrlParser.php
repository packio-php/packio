<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure;

use InvalidArgumentException;

use function preg_match;
use function preg_replace;
use function sprintf;
use function trim;

final class BitbucketRepositoryUrlParser
{
    /**
     * @return array{workspace: string, repository: string, normalized_url: string}
     */
    public function parse(string $url): array
    {
        $normalized = (string) preg_replace('#\.git$#', '', trim($url));

        if (1 === preg_match('#^https://bitbucket\.org/(?P<workspace>[^/]+)/(?P<repository>[^/]+)$#', $normalized, $matches)) {
            return $this->result($matches['workspace'], $matches['repository']);
        }

        if (1 === preg_match('#^git@bitbucket\.org:(?P<workspace>[^/]+)/(?P<repository>[^/]+)$#', $normalized, $matches)) {
            return $this->result($matches['workspace'], $matches['repository']);
        }

        throw new InvalidArgumentException('Only bitbucket.org repository URLs are supported for this flow.');
    }

    /**
     * @return array{workspace: string, repository: string, normalized_url: string}
     */
    private function result(string $workspace, string $repository): array
    {
        $workspace  = trim($workspace);
        $repository = trim($repository);

        if ('' === $workspace || '' === $repository) {
            throw new InvalidArgumentException('A Bitbucket repository URL must include a workspace and repository slug.');
        }

        return [
            'workspace'      => $workspace,
            'repository'     => $repository,
            'normalized_url' => sprintf('https://bitbucket.org/%s/%s', $workspace, $repository),
        ];
    }
}
