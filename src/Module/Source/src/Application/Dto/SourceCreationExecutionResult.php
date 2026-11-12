<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

use Packio\Source\Application\Service\SourceCreationOutcome;
use Packio\Source\Application\Service\SourceWebhookRegistrationOutcome;

final readonly class SourceCreationExecutionResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public SourceCreationOutcome $outcome,
        public array $data = [],
        public ?string $error = null,
        public SourceWebhookRegistrationOutcome $webhook = SourceWebhookRegistrationOutcome::Skipped,
    ) {}
}
