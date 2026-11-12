<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\GitlabSelfHosted;

use Packio\Publication\Infrastructure\Git\GitReference;

interface GitlabSelfHostedComposerMetadataFetcherInterface
{
    public function snapshot(
        string $namespace,
        string $repository,
        GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials
    ): GitlabSelfHostedRepositorySnapshot;

    public function fetch(
        string $namespace,
        string $repository,
        ?string $ref,
        GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials
    ): GitlabSelfHostedPackageMetadata;

    public function fetchReference(
        string $namespace,
        string $repository,
        GitReference $gitReference,
        string $defaultBranch,
        GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials
    ): GitlabSelfHostedPackageMetadata;
}
