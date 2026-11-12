<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Adapter;

use Packio\Organization\Application\Port\SourceDeletionSchedulerPort;
use Packio\Source\Api\SourceApiFacadeInterface;

final readonly class SourceDeletionSchedulerAdapter implements SourceDeletionSchedulerPort
{
    public function __construct(private SourceApiFacadeInterface $sourceApiFacade) {}

    public function sourcesForOrganization(string $organizationId): array
    {
        return $this->sourceApiFacade->sourcesForOrganization($organizationId);
    }

    public function scheduleSourceDelete(string $sourceId, string $organizationId, string $deletionRunId): bool
    {
        return $this->sourceApiFacade->scheduleSourceDelete(
            $organizationId,
            $sourceId,
            $deletionRunId,
            'organization.delete',
        );
    }
}
