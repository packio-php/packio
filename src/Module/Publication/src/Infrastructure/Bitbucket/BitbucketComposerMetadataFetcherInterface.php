<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Bitbucket;

use Packio\Publication\Infrastructure\Git\GitReference;

interface BitbucketComposerMetadataFetcherInterface
{
    public function snapshot(
        string $workspace,
        string $repository,
        ?BitbucketAccessCredentials $bitbucketAccessCredentials = null
    ): BitbucketRepositorySnapshot;

    public function fetch(
        string $workspace,
        string $repository,
        ?string $ref = null,
        ?BitbucketAccessCredentials $bitbucketAccessCredentials = null,
    ): BitbucketPackageMetadata;

    public function fetchReference(
        string $workspace,
        string $repository,
        GitReference $gitReference,
        string $defaultBranch,
        ?BitbucketAccessCredentials $bitbucketAccessCredentials = null
    ): BitbucketPackageMetadata;
}
