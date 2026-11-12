<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider;

use function bin2hex;
use function random_bytes;

final readonly class WebhookSecretGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
