<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Dto;

use function array_filter;
use function count;

final readonly class SourceProviderInstanceDependencies
{
    /**
     * @param list<array{id: string, name: string, url: string, provider: string, status: string}>                 $sources
     * @param list<array{id: string, accountName: string, provider: string, authStrategy: string, status: string}> $authorizations
     */
    public function __construct(
        public string $instanceId,
        public string $label,
        public string $baseUrl,
        public string $providerType,
        public array $sources,
        public array $authorizations,
    ) {}

    public function sourceCount(): int
    {
        return count($this->sources);
    }

    public function authorizationCount(): int
    {
        return count($this->authorizations);
    }

    public function activeAuthorizationCount(): int
    {
        return count(array_filter($this->authorizations, static fn (array $authorization): bool => 'active' === $authorization['status']));
    }

    public function inactiveAuthorizationCount(): int
    {
        return count(array_filter($this->authorizations, static fn (array $authorization): bool => 'inactive' === $authorization['status']));
    }
}
