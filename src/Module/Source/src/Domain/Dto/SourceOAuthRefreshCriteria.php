<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Dto;

use Cake\Chronos\Chronos;

final readonly class SourceOAuthRefreshCriteria
{
    public function __construct(
        public Chronos $tokenExpiresBefore,
        public Chronos $lastUsedBefore,
        public Chronos $updatedBefore,
        public ?string $provider = null,
        public ?string $providerInstanceId = null,
        public ?string $organizationId = null,
        public int $limit = 100,
    ) {}
}
