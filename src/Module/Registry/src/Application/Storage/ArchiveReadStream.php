<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Storage;

use Cake\Chronos\Chronos;
use Psr\Http\Message\StreamInterface;

final readonly class ArchiveReadStream
{
    public function __construct(
        public StreamInterface $body,
        public ?int $size,
        public string $contentType,
        public ?string $etag = null,
        public ?Chronos $lastModified = null,
        public ?string $sha256 = null,
        public ?string $bucket = null,
    ) {}
}
