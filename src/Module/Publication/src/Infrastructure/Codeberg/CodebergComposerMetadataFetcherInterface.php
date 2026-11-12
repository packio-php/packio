<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Codeberg;

use Packio\Publication\Infrastructure\Git\GitReference;

interface CodebergComposerMetadataFetcherInterface
{
    public function snapshot(
        string $owner,
        string $repository,
        ?CodebergAccessCredentials $codebergAccessCredentials = null
    ): CodebergRepositorySnapshot;

    public function fetch(
        string $owner,
        string $repository,
        ?string $ref = null,
        ?CodebergAccessCredentials $codebergAccessCredentials = null,
    ): CodebergPackageMetadata;

    public function fetchReference(
        string $owner,
        string $repository,
        GitReference $gitReference,
        string $defaultBranch,
        ?CodebergAccessCredentials $codebergAccessCredentials = null
    ): CodebergPackageMetadata;
}
