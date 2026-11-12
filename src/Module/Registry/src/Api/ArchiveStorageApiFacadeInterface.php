<?php

declare(strict_types=1);

namespace Packio\Registry\Api;

interface ArchiveStorageApiFacadeInterface
{
    public function localArchiveDirectory(): ?string;
}
