<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

final readonly class SourceProviderDto
{
    /**
     * @param array<string, bool> $capabilities
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public array $capabilities = [],
        public ?string $oauthProvider = null,
        public bool $oauthEnabled = false,
        public bool $oauthAvailable = false,
    ) {}
}
