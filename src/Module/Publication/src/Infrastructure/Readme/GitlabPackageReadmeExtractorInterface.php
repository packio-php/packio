<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use Packio\Publication\Infrastructure\Gitlab\GitlabAccessCredentials;

interface GitlabPackageReadmeExtractorInterface
{
    public function extractGitlabReadmeHtml(
        string $namespace,
        string $repository,
        string $reference,
        ?GitlabAccessCredentials $gitlabAccessCredentials = null
    ): ?string;
}
