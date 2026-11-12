<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Storage;

use Cake\Chronos\Chronos;

final readonly class StoredArchive
{
    public function __construct(
        public string $key,
        public ?string $bucket,
        public int $size,
        public string $sha256,
        public ?string $etag,
        public string $contentType,
        public Chronos $storedAt,
    ) {}
}
