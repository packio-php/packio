<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Request;

use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

final readonly class ResetPasswordLinkRequest
{
    public function __construct(
        #[Required]
        #[Regex('#^[A-Za-z0-9_-]{1,128}$#')]
        public string $selector = '',
        #[Required]
        #[Regex('#^[A-Za-z0-9_-]{1,128}$#')]
        public string $token = '',
    ) {}
}
