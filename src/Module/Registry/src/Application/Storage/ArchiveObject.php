<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Storage;

final readonly class ArchiveObject
{
    public function __construct(public string $key, public ?int $size = null) {}
}
