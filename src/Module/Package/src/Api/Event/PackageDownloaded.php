<?php

declare(strict_types=1);

namespace Packio\Package\Api\Event;

final readonly class PackageDownloaded
{
    public function __construct(
        public string $organizationId,
        public string $packageName,
        public string $version,
    ) {}
}
