<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Request;

use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Required;

final readonly class AdminInvitationCreateRequest
{
    public function __construct(#[Required] #[In(['member', 'owner'])] public string $role = '') {}
}
