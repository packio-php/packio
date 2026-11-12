<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Github;

use Packio\Publication\Infrastructure\Git\GitReference;

interface GithubComposerMetadataFetcherInterface
{
    public function snapshot(
        string $owner,
        string $repository,
        ?GithubAccessCredentials $githubAccessCredentials = null
    ): GithubRepositorySnapshot;

    public function fetch(
        string $owner,
        string $repository,
        ?string $ref = null,
        ?GithubAccessCredentials $githubAccessCredentials = null
    ): GithubPackageMetadata;

    public function fetchReference(
        string $owner,
        string $repository,
        GitReference $gitReference,
        string $defaultBranch,
        ?GithubAccessCredentials $githubAccessCredentials = null
    ): GithubPackageMetadata;
}
