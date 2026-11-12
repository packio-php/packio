<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\GenericGit;

use Packio\Publication\Infrastructure\Git\GitReference;

interface GenericGitComposerMetadataFetcherInterface
{
    public function snapshot(string $cloneUrl, GenericGitAccessCredentials $genericGitAccessCredentials): GenericGitRepositorySnapshot;

    public function fetch(
        string $cloneUrl,
        ?string $ref,
        GenericGitAccessCredentials $genericGitAccessCredentials
    ): GenericGitPackageMetadata;

    public function fetchReference(
        string $cloneUrl,
        GitReference $gitReference,
        string $defaultBranch,
        GenericGitAccessCredentials $genericGitAccessCredentials
    ): GenericGitPackageMetadata;
}
