<?php

declare(strict_types=1);

namespace Packio\Application\Security\Honeypot;

use function bin2hex;
use function random_bytes;

final readonly class HoneypotFieldNameGenerator
{
    public function generate(): string
    {
        return HoneypotFieldContract::FIELD_PREFIX . bin2hex(random_bytes(8));
    }
}
