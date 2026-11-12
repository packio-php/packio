<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Webhook;

use RuntimeException;

final class InvalidWebhookRequest extends RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 401)
    {
        parent::__construct($message);
    }
}
