<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Source\Application\Dto\SourceDeletionExecutionResult;
use Psr\Log\LoggerInterface;

interface SourceDeletionExecutionInterface
{
    public function execute(
        string $sourceId,
        string $organizationId,
        string $deletionRunId,
        string $origin,
        LoggerInterface $jobLogger
    ): SourceDeletionExecutionResult;
}
