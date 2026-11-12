<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure;

use RuntimeException;

use function array_map;
use function explode;
use function implode;
use function rawurlencode;
use function sprintf;
use function trim;

final readonly class GitlabDistArchiveFetcher
{
    public function __construct(private GenericGitDistArchiveFetcher $genericGitDistArchiveFetcher) {}

    public function fetchArchive(
        string $owner,
        string $repository,
        string $reference,
        ?string $token = null,
        string $authStrategy = '',
    ): DistArchiveStream {
        $cloneUrl = sprintf(
            'https://gitlab.com/%s/%s.git',
            $this->encodePath($owner),
            rawurlencode($repository),
        );

        if (null === $token || '' === trim($token)) {
            return $this->fetchWithUsername($repository, $reference, null, $cloneUrl, '');
        }

        $lastException = null;
        foreach ($this->authUsernames($authStrategy) as $authUsername) {
            try {
                return $this->fetchWithUsername($repository, $reference, $token, $cloneUrl, $authUsername);
            } catch (RuntimeException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException ?? new RuntimeException('Failed to build GitLab archive.');
    }

    private function fetchWithUsername(
        string $repository,
        string $reference,
        ?string $token,
        string $cloneUrl,
        string $authUsername,
    ): DistArchiveStream {
        return $this->genericGitDistArchiveFetcher->fetchArchive($repository, $reference, $token, [
            'clone_url'     => $cloneUrl,
            'auth_scheme'   => '' !== $authUsername ? 'basic' : '',
            'auth_username' => $authUsername,
        ]);
    }

    /**
     * @return list<string>
     */
    private function authUsernames(string $authStrategy): array
    {
        if ('token' === $authStrategy) {
            return ['git', 'oauth2'];
        }

        return ['oauth2', 'git'];
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map(
            rawurlencode(...),
            explode('/', trim($path, '/')),
        ));
    }
}
