<?php

declare(strict_types=1);

namespace Common\Runtime;

use Monolog\ResettableInterface;
use Psr\Log\LoggerInterface;

final readonly class LoggerRuntimeResetter implements RuntimeResetterInterface
{
    public function __construct(private ?LoggerInterface $logger = null) {}

    public function reset(): void
    {
        if ($this->logger instanceof ResettableInterface) {
            $this->logger->reset();
        }
    }
}
