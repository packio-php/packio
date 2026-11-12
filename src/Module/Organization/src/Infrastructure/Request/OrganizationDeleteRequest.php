<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Request;

use Yiisoft\Validator\Rule\Required;

final readonly class OrganizationDeleteRequest
{
    public function __construct(#[Required] public string $confirmationName = '') {}
}
