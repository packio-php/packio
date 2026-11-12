<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Storage;

use Psr\Http\Message\StreamInterface;

readonly class DistArchiveStream
{
    public function __construct(
        public StreamInterface $body,
        public string $contentType = 'application/zip',
        public ?int $contentLength = null,
        public string $fileName = 'package.zip',
    ) {}
}
