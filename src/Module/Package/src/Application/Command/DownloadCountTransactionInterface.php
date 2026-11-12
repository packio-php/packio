<?php

declare(strict_types=1);

namespace Packio\Package\Application\Command;

interface DownloadCountTransactionInterface
{
    /** @param callable(): bool $callback */
    public function run(callable $callback): bool;
}
