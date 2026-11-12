<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Request;

use Packio\Identity\Api\UserBanReason;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

use function trim;

final readonly class AdminUserUnbanRequest
{
    #[Required]
    #[Length(min: UserBanReason::MIN_LENGTH, max: UserBanReason::MAX_LENGTH)]
    public string $note;

    public function __construct(
        string $note = '',
    ) {
        $this->note = trim($note);
    }

    public function normalizedNote(): string
    {
        return $this->note;
    }
}
