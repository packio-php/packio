<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use Packio\Publication\Infrastructure\GitlabSelfHosted\GitlabSelfHostedAccessCredentials;

interface GitlabSelfHostedPackageReadmeExtractorInterface
{
    public function extractGitlabSelfHostedReadmeHtml(
        string $namespace,
        string $repository,
        string $reference,
        ?GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials = null,
    ): ?string;
}
