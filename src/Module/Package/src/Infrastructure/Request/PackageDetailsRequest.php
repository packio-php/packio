<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Request;

use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

final readonly class PackageDetailsRequest
{
    public function __construct(
        #[Required]
        #[Regex('#^[A-Za-z0-9._-]+$#')]
        public string $vendor,
        #[Required]
        #[Regex('#^[A-Za-z0-9._-]+(?:~[A-Za-z0-9._-]+)?$#')]
        public string $package,
    ) {}
}
