<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Source\Application\Dto\SourceSyncExecutionResult;
use Psr\Log\LoggerInterface;

interface SourceSyncExecutionInterface
{
    public function execute(string $sourceId, string $organizationId, LoggerInterface $jobLogger): SourceSyncExecutionResult;
}
