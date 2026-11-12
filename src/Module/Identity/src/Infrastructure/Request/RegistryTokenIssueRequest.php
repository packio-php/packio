<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Request;

use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

final readonly class RegistryTokenIssueRequest
{
    public function __construct(#[Required] #[Length(min: 1, max: 100)] public string $name = '') {}
}
