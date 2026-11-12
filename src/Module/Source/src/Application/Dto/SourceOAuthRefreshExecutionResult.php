<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

use Packio\Source\Application\Service\SourceOAuthRefreshReason;

final readonly class SourceOAuthRefreshExecutionResult
{
    public function __construct(
        public string $sourceOAuthAccountId,
        public string $providerAuthorizationId,
        public string $provider,
        public SourceOAuthRefreshReason $reason,
        public bool $refreshed,
    ) {}
}
