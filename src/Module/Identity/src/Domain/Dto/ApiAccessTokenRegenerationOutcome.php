<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Dto;

use Packio\Identity\Domain\Entity\ApiAccessToken;

final readonly class ApiAccessTokenRegenerationOutcome
{
    public function __construct(
        public ApiAccessToken $revoked,
        public ApiAccessToken $replacement,
    ) {}
}
