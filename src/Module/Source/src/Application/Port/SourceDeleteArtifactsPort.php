<?php

declare(strict_types=1);

namespace Packio\Source\Application\Port;

use Packio\Source\Application\Dto\SourceDeleteArtifactsResult;

interface SourceDeleteArtifactsPort
{
    public function deleteRelatedArtifacts(
        string $sourceId,
        string $organizationId,
        ?string $deletionRunId = null,
        string $origin = '',
    ): SourceDeleteArtifactsResult;
}
