<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Storage;

use Psr\Http\Message\StreamInterface;

final readonly class ReleaseArchiveDownload
{
    public function __construct(public StreamInterface $body, public string $fileName, public string $contentType, public ?int $size) {}
}
