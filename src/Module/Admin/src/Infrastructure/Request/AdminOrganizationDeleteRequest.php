<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Request;

use Yiisoft\Validator\Rule\Required;

final readonly class AdminOrganizationDeleteRequest
{
    public function __construct(#[Required] public string $confirmationName = '') {}
}
