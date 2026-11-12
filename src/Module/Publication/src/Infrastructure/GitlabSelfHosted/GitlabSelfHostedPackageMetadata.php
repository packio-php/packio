<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\GitlabSelfHosted;

use Packio\Publication\Infrastructure\Git\GitReference;

final readonly class GitlabSelfHostedPackageMetadata
{
    public string $refName;
    public string $commitReference;
    public string $refType;

    /**
     * @param array<string, mixed> $composer
     */
    public function __construct(
        public string $namespace,
        public string $repository,
        public string $baseUrl,
        public string $reference,
        public array $composer,
        public string $defaultBranch,
        ?string $commitReference = null,
        ?string $refType = null,
    ) {
        $this->refName         = $this->reference;
        $this->commitReference = $commitReference ?? $this->reference;
        $this->refType         = $refType ?? GitReference::TYPE_BRANCH;
    }
}
