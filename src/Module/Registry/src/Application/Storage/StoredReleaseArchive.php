<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Storage;

final readonly class StoredReleaseArchive
{
    public function __construct(public string $key, public string $fileName, public string $contentType, public ?int $size) {}
}
