<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Dto;

use Packio\Identity\Domain\Entity\RegistryAccessToken;

final readonly class RegistryAccessTokenRegenerationOutcome
{
    public function __construct(
        public RegistryAccessToken $revoked,
        public RegistryAccessToken $replacement,
    ) {}
}
