<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use Packio\Publication\Infrastructure\Github\GithubAccessCredentials;

interface PackageReadmeExtractorInterface
{
    public function extractGithubReadmeHtml(
        string $owner,
        string $repository,
        string $reference,
        ?GithubAccessCredentials $githubAccessCredentials = null
    ): ?string;
}
