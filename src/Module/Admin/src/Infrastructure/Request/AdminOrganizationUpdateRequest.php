<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Request;

use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

final readonly class AdminOrganizationUpdateRequest
{
    public function __construct(
        #[Required]
        #[Length(min: 2, max: 50)]
        public string $name = '',
        #[Required]
        #[Regex('#^[A-Za-z0-9_-]+$#')]
        public string $slug = '',
        public string $anonymousAccess = '',
    ) {}
}
