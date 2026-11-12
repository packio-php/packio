<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Request;

use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

final readonly class AdminSourceDeleteRequest
{
    public function __construct(
        #[Required]
        #[Length(min: 1, max: 255)]
        public string $confirmationName = '',
    ) {}
}
