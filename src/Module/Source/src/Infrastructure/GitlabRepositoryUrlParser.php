<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure;

use InvalidArgumentException;

use function array_pop;
use function count;
use function explode;
use function implode;
use function preg_match;
use function preg_replace;
use function sprintf;
use function trim;

final class GitlabRepositoryUrlParser
{
    /**
     * @return array{namespace: string, repository: string, normalized_url: string}
     */
    public function parse(string $url): array
    {
        $normalized = (string) preg_replace('#\.git$#', '', trim($url));

        if (1 === preg_match('#^https://gitlab\.com/(?P<path>.+)$#', $normalized, $matches)) {
            return $this->parsePath($matches['path']);
        }

        if (1 === preg_match('#^git@gitlab\.com:(?P<path>.+)$#', $normalized, $matches)) {
            return $this->parsePath($matches['path']);
        }

        throw new InvalidArgumentException('Only gitlab.com repository URLs are supported for this flow.');
    }

    /**
     * @return array{namespace: string, repository: string, normalized_url: string}
     */
    private function parsePath(string $path): array
    {
        $segments = explode('/', trim($path, '/'));
        if (count($segments) < 2) {
            throw new InvalidArgumentException('A GitLab repository URL must include a namespace and repository name.');
        }

        $repository = array_pop($segments);
        $namespace  = implode('/', $segments);

        return [
            'namespace'      => $namespace,
            'repository'     => $repository,
            'normalized_url' => sprintf('https://gitlab.com/%s/%s', $namespace, $repository),
        ];
    }
}
