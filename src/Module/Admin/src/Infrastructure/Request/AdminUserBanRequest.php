<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Request;

use Packio\Identity\Api\UserBanReason;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

use function trim;

final readonly class AdminUserBanRequest
{
    #[Required]
    #[Length(min: UserBanReason::MIN_LENGTH, max: UserBanReason::MAX_LENGTH)]
    public string $reason;

    public function __construct(
        string $reason = '',
    ) {
        $this->reason = trim($reason);
    }

    public function normalizedReason(): string
    {
        return $this->reason;
    }
}
