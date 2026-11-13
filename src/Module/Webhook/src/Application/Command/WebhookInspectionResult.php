<?php

declare(strict_types=1);

namespace Packio\Webhook\Application\Command;

final readonly class WebhookInspectionResult
{
    public const string REPOSITORY_MISMATCH = 'Webhook repository does not match the configured source.';

    public function __construct(
        public string $status,
        public string $message,
    ) {}

    public static function processed(string $message): self
    {
        return new self('processed', $message);
    }

    public static function ignored(string $message): self
    {
        return new self('ignored', $message);
    }

    public static function rejected(string $message): self
    {
        return new self('rejected', $message);
    }
}
