<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

use Cake\Chronos\Chronos;
use Packio\Source\Application\Service\SourceOAuthRefreshReason;

final readonly class SourceOAuthRefreshCandidate
{
    public function __construct(
        public string $sourceOAuthAccountId,
        public string $provider,
        public SourceOAuthRefreshReason $reason,
        public ?string $providerInstanceId = null,
        public ?Chronos $tokenExpiresAt = null,
        public ?Chronos $lastUsedAt = null,
    ) {}
}
