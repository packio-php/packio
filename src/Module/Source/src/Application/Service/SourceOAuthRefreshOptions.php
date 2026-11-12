<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

final readonly class SourceOAuthRefreshOptions
{
    /**
     * @param array<string, SourceOAuthRefreshProviderOptions> $providers
     */
    public function __construct(
        public string $refreshBefore = '24 hours',
        public string $idleBefore = '30 days',
        public string $minimumRefreshInterval = '1 day',
        public int $limit = 100,
        public array $providers = [],
    ) {}

    public function provider(string $provider): ?SourceOAuthRefreshProviderOptions
    {
        return $this->providers[$provider] ?? null;
    }
}
