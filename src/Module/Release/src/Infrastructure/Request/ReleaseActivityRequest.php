<?php

declare(strict_types=1);

namespace Packio\Release\Infrastructure\Request;

use Yiisoft\Validator\Rule\Required;

final readonly class ReleaseActivityRequest
{
    public function __construct(
        public string $active = '',
        #[Required]
        public string $version = '',
    ) {}
}
