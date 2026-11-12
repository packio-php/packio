<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Request;

use Yiisoft\Validator\Rule\Required;

final readonly class OrganizationInviteAcceptRequest
{
    public function __construct(#[Required] public string $token = '') {}
}
