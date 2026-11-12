<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use Packio\Publication\Infrastructure\Codeberg\CodebergAccessCredentials;

interface CodebergPackageReadmeExtractorInterface
{
    public function extractCodebergReadmeHtml(
        string $owner,
        string $repository,
        string $reference,
        ?CodebergAccessCredentials $codebergAccessCredentials = null
    ): ?string;
}
