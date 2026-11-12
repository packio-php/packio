<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Dto;

final readonly class SourceProviderInstanceCleanupResult
{
    public function __construct(
        public int $deletedInactiveAuthorizationsCount,
        public int $deletedSourceOAuthAccountsCount,
    ) {}
}
