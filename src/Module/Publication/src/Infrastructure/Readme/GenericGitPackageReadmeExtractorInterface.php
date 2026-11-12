<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use Packio\Publication\Infrastructure\GenericGit\GenericGitAccessCredentials;

interface GenericGitPackageReadmeExtractorInterface
{
    public function extractGenericGitReadmeHtml(
        string $cloneUrl,
        string $reference,
        ?GenericGitAccessCredentials $genericGitAccessCredentials = null,
    ): ?string;
}
