<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Request;

use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

final readonly class EmailVerificationCodeRequest
{
    public function __construct(#[Required] #[Length(exactly: 6)] public string $code) {}
}
