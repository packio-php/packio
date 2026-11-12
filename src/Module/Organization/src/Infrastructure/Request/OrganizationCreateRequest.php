<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Request;

use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

final readonly class OrganizationCreateRequest
{
    public function __construct(#[Required] #[Length(min: 2, max: 50)] #[Regex('#^[a-zA-Z0-9\s\-\.]+$#')] public string $name = '') {}
}
