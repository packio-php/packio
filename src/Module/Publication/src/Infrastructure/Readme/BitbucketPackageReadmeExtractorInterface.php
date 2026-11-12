<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use Packio\Publication\Infrastructure\Bitbucket\BitbucketAccessCredentials;

interface BitbucketPackageReadmeExtractorInterface
{
    public function extractBitbucketReadmeHtml(
        string $workspace,
        string $repository,
        string $reference,
        ?BitbucketAccessCredentials $bitbucketAccessCredentials = null
    ): ?string;
}
