<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Request;

use Yiisoft\Validator\Rule\Compare;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

final readonly class ResetPasswordRequest
{
    public function __construct(
        #[Required]
        #[Regex('#^[A-Za-z0-9_-]{1,128}$#')]
        public string $selector = '',
        #[Required]
        #[Regex('#^[A-Za-z0-9_-]{1,128}$#')]
        public string $token = '',
        #[Required]
        #[Length(min: 8)]
        #[Regex(pattern: '/\d/', message: 'Password must contain at least one digit', )]
        #[Regex(pattern: '/[^A-Za-z0-9]/', message: 'Password must contain at least one special character', )]
        public string $password = '',
        #[Required]
        #[Compare(targetProperty: 'password', message: 'Password confirmation does not match.', type: CompareType::STRING, operator: '===')]
        public string $passwordConfirmation = '',
    ) {}
}
