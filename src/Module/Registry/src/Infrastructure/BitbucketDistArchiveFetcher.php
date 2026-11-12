<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure;

use function rawurlencode;
use function sprintf;

final readonly class BitbucketDistArchiveFetcher
{
    public function __construct(private GenericGitDistArchiveFetcher $genericGitDistArchiveFetcher) {}

    public function fetchArchive(string $owner, string $repository, string $reference, ?string $token = null): DistArchiveStream
    {
        return $this->genericGitDistArchiveFetcher->fetchArchive($repository, $reference, $token, [
            'clone_url'     => sprintf(
                'https://bitbucket.org/%s/%s.git',
                rawurlencode($owner),
                rawurlencode($repository),
            ),
            'auth_scheme'   => 'basic',
            'auth_username' => 'x-token-auth',
        ]);
    }
}
