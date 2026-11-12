<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Gitlab;

use Packio\Publication\Infrastructure\Git\GitReference;

interface GitlabComposerMetadataFetcherInterface
{
    public function snapshot(
        string $namespace,
        string $repository,
        ?GitlabAccessCredentials $gitlabAccessCredentials = null
    ): GitlabRepositorySnapshot;

    public function fetch(
        string $namespace,
        string $repository,
        ?string $ref = null,
        ?GitlabAccessCredentials $gitlabAccessCredentials = null
    ): GitlabPackageMetadata;

    public function fetchReference(
        string $namespace,
        string $repository,
        GitReference $gitReference,
        string $defaultBranch,
        ?GitlabAccessCredentials $gitlabAccessCredentials = null
    ): GitlabPackageMetadata;
}
