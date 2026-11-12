<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter\Worker;

use Packio\Worker\Api\Job\JobLoggerInterface;
use Psr\Log\AbstractLogger;
use Stringable;

final class SourceJobLogger extends AbstractLogger
{
    public function __construct(private readonly JobLoggerInterface $jobLogger) {}

    public function log($level, string|Stringable $message, array $context = []): void
    {
        match ($level) {
            'emergency', 'alert', 'critical', 'error' => $this->jobLogger->error((string) $message, $context),
            'warning'                                 => $this->jobLogger->warning((string) $message, $context),
            'debug'                                   => $this->jobLogger->debug((string) $message, $context),
            default                                   => $this->jobLogger->info((string) $message, $context),
        };
    }
}
