<?php

declare(strict_types=1);

namespace Packio\Webhook\Api\Dto;

final readonly class WebhookResultDto
{
    public function __construct(public int $statusCode, public string $message, public string $provider = '') {}
}
