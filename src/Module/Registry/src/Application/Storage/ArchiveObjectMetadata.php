<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Storage;

final readonly class ArchiveObjectMetadata
{
    public function __construct(
        public string $contentType = 'application/zip',
        public ?int $contentLength = null,
        public ?string $sha256 = null,
        public ?string $sourceProvider = null,
        public ?string $sourceReference = null,
    ) {}
}
