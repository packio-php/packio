<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Repository;

use Packio\Source\Domain\Dto\SourceProviderInstanceCleanupResult;

interface SourceProviderInstanceCleanupRepositoryInterface
{
    public function deleteStrict(string $organizationId, string $providerInstanceId): void;

    public function deleteWithInactiveAuthorizations(
        string $organizationId,
        string $providerInstanceId
    ): SourceProviderInstanceCleanupResult;
}
