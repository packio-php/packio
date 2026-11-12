<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

final readonly class WebhookRegistrationResultDto
{
    public function __construct(
        public bool $success,
        public string $externalId,
        public string $message,
        public bool $alreadyExists = false,
        public ?string $authHealth = null,
        public ?string $authFailureReason = null,
    ) {}
}
