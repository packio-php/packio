<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Adapter;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Application\Port\IdentityServicePort;

final readonly class IdentityServiceAdapter implements IdentityServicePort
{
    public function __construct(private IdentityApiFacadeInterface $identityApiFacade) {}

    public function revokeRegistryTokensForOrganization(string $organizationId): int
    {
        return $this->identityApiFacade->revokeRegistryTokensForOrganization($organizationId);
    }
}
