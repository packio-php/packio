<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Request;

use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Required;

final readonly class LoginRequest
{
    public function __construct(
        #[Required]
        #[Email]
        public string $email = '',
        #[Required]
        public string $password = '',
        public string $redirectTo = ''
    ) {}
}
