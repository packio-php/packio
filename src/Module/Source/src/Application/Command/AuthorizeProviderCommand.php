<?php

declare(strict_types=1);

namespace Packio\Source\Application\Command;

final readonly class AuthorizeProviderCommand
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $organizationId,
        public string $provider,
        public string $authorizationStrategy = 'token',
        public array $payload = [],
    ) {}
}
