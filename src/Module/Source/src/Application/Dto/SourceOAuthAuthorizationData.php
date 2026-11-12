<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

use function trim;

final readonly class SourceOAuthAuthorizationData
{
    public function __construct(
        public string $sourceOAuthAccountId,
        public string $accessToken,
        public ?string $providerUsername,
        public ?string $providerEmail,
        public ?string $providerInstanceId = null,
        public ?string $providerBaseUrl = null,
    ) {}

    public function accountName(): string
    {
        $providerUsername = trim((string) $this->providerUsername);
        if ('' !== $providerUsername) {
            return $providerUsername;
        }

        return trim((string) $this->providerEmail);
    }
}
