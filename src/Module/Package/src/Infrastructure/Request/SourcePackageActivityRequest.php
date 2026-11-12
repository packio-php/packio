<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Request;

final readonly class SourcePackageActivityRequest
{
    public function __construct(public string $active = '') {}
}
