<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Request;

final readonly class SourceArchiveRequest
{
    public function __construct(public string $archived = '1') {}
}
