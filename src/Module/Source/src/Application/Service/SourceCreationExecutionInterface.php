<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Source\Application\Command\RegisterSourceCommand;
use Packio\Source\Application\Dto\SourceCreationExecutionResult;
use Psr\Log\LoggerInterface;

interface SourceCreationExecutionInterface
{
    public function execute(
        RegisterSourceCommand $registerSourceCommand,
        string $requestedByUserId,
        LoggerInterface $jobLogger
    ): SourceCreationExecutionResult;
}
