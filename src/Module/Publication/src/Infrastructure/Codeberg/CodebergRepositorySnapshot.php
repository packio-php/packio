<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Codeberg;

use Packio\Publication\Infrastructure\Git\GitReference;

final readonly class CodebergRepositorySnapshot
{
    /**
     * @param list<GitReference|string> $tags
     * @param list<GitReference|string> $branches
     */
    public function __construct(
        public string $defaultBranch,
        public array $tags,
        public array $branches = []
    ) {}
}
