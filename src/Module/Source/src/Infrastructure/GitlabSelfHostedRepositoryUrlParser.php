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

final class GitlabSelfHostedRepositoryUrlParser
{
    /**
     * @return array{namespace: string, repository: string, normalized_url: string, base_url: string}
     */
    public function parse(string $url): array
    {
        $normalized = (string) preg_replace('#\.git$#', '', trim($url));

        if (1 === preg_match('#^https://(?P<host>[^/]+)/(?P<path>.+)$#', $normalized, $matches)) {
            $host = $matches['host'];
            if ('gitlab.com' === $host) {
                throw new InvalidArgumentException(
                    'git@gitlab.com: URLs are not supported for self-hosted GitLab. Please use the standard GitLab provider instead.',
                );
            }

            return $this->parsePath($matches['path'], $host);
        }

        if (1 === preg_match('#^git@(?P<host>[^:]+):(?P<path>.+)$#', $normalized, $matches)) {
            $host = $matches['host'];
            if ('gitlab.com' === $host) {
                throw new InvalidArgumentException(
                    'SSH URLs for gitlab.com are not supported for self-hosted GitLab. Please use the standard GitLab provider instead.',
                );
            }

            return $this->parsePath($matches['path'], $host);
        }

        throw new InvalidArgumentException('Invalid GitLab self-hosted repository URL.');
    }

    /**
     * @return array{namespace: string, repository: string, normalized_url: string, base_url: string}
     */
    private function parsePath(string $path, string $host): array
    {
        $segments = explode('/', trim($path, '/'));
        if (count($segments) < 2) {
            throw new InvalidArgumentException('A GitLab repository URL must include a namespace and repository name.');
        }

        $repository = array_pop($segments);
        $namespace  = implode('/', $segments);
        $baseUrl    = sprintf('https://%s', $host);

        return [
            'namespace'      => $namespace,
            'repository'     => $repository,
            'normalized_url' => sprintf('%s/%s/%s', $baseUrl, $namespace, $repository),
            'base_url'       => $baseUrl,
        ];
    }
}
