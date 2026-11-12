<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Request;

use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\Uuid;

final readonly class ProfileOAuthDisconnectRequest
{
    public function __construct(#[Required] #[Uuid] public string $accountId = '') {}
}
