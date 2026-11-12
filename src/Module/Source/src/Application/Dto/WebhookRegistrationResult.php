<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

final readonly class WebhookRegistrationResult
{
    public function __construct(
        public bool $success,
        public string $externalId,
        public string $message,
        public bool $alreadyExists = false,
        public ?string $authHealth = null,
        public ?string $authFailureReason = null,
    ) {}

    public static function success(string $externalId, string $message = ''): self
    {
        return new self(true, $externalId, $message);
    }

    public static function alreadyExists(string $externalId, string $message = ''): self
    {
        return new self(true, $externalId, $message, true);
    }

    public static function failure(string $message, ?string $authHealth = null, ?string $authFailureReason = null): self
    {
        return new self(false, '', $message, false, $authHealth, $authFailureReason);
    }
}
